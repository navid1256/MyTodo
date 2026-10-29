import { execFile, spawn } from 'node:child_process';
import { resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { promisify } from 'node:util';
import { createServer, loadEnv } from 'vite';

const projectRoot = fileURLToPath(new URL('../', import.meta.url));
const minuteMilliseconds = 60_000;

/** Runs at calendar-minute boundaries without overlapping or interrupting an active delivery. */
export function startReminderScheduler(runWorker, log = console.log) {
    let timer;
    let activeWorker = null;
    let stopping = false;

    /** Recalculates the next minute so worker duration never accumulates scheduling drift. */
    function scheduleNextMinute() {
        timer = setTimeout(tick, minuteMilliseconds - Date.now() % minuteMilliseconds);
    }

    /** Starts one worker, or skips this minute when the preceding run is still active. */
    function tick() {
        if (stopping) return;
        scheduleNextMinute();
        if (activeWorker) {
            log('[reminders] Previous worker still running; skipping this minute.');
            return;
        }
        log('[reminders] Running due reminders.');
        activeWorker = Promise.resolve()
            .then(() => { if (!stopping) return runWorker(); })
            .catch(() => log('[reminders] Worker failed; check its CLI output and PHP configuration. Next attempt is next minute.'))
            .finally(() => { activeWorker = null; });
    }

    scheduleNextMinute();
    return {
        /** Stops new runs and allows the current PHP delivery to finish before shutdown. */
        async stop() {
            stopping = true;
            clearTimeout(timer);
            await activeWorker;
        },
    };
}

/** Launches the existing PHP pool without a shell and keeps it isolated from terminal Ctrl+C. */
export function runReminderWorker(phpBinary, spawnProcess = spawn) {
    return new Promise((resolveWorker, rejectWorker) => {
        const child = spawnProcess(phpBinary, [resolve(projectRoot, 'process/send-reminders.php'), '--send'], {
            cwd: projectRoot,
            shell: false,
            windowsHide: true,
            // Separate process group protects in-flight sends; pipes and the child reference keep shutdown waiting.
            detached: true,
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        child.stdout?.pipe(process.stdout, { end: false });
        child.stderr?.pipe(process.stderr, { end: false });
        child.once('error', () => rejectWorker(new Error('Reminder worker could not start. Check MYTODO_PHP_BINARY.')));
        child.once('close', code => {
            if (code === 0) resolveWorker();
            else rejectWorker(new Error('Reminder worker exited unsuccessfully.'));
        });
    });
}

/** Starts Vite first, then local reminders; injectable boundaries keep verification free of real pushes. */
export async function startDevelopment({
    environment = loadEnv('development', projectRoot, ['APP_ENV', 'MYTODO_PHP_BINARY']),
    createViteServer = createServer,
    runWorker,
} = {}) {
    if (environment.APP_ENV?.trim().toLowerCase() === 'production' || process.env.NODE_ENV === 'production') {
        throw new Error('This command is for development only. Use a production scheduler instead.');
    }
    const phpBinary = environment.MYTODO_PHP_BINARY?.trim() || 'php';
    try {
        await promisify(execFile)(phpBinary, ['--version'], { cwd: projectRoot, windowsHide: true, timeout: 5_000 });
    } catch {
        throw new Error('PHP CLI is unavailable. Set MYTODO_PHP_BINARY to the PHP executable in .env.local.');
    }

    let scheduler = null;
    let stopping = false;
    const server = await createViteServer({
        configFile: resolve(projectRoot, 'vite.config.js'),
        root: resolve(projectRoot, 'public'),
        mode: 'development',
        plugins: [{
            name: 'mytodo-dev-reminder-shutdown',
            /** Makes Vite's own exit path wait for deliveries, including after a config restart. */
            configureServer(viteServer) {
                const closeVite = viteServer.close.bind(viteServer);
                /** Preserves ordinary Vite restarts but drains the worker during final shutdown. */
                viteServer.close = async () => {
                    if (stopping && scheduler) await scheduler.stop();
                    await closeVite();
                };
            },
        }],
    });
    try {
        await server.listen();
    } catch (error) {
        await server.close();
        throw error;
    }
    server.printUrls();
    // console.log('[reminders] REAL delivery enabled for ALL due reminders in the configured local database.');
    // console.log('[reminders] First run: next minute boundary. Ctrl+C stops scheduling and waits for any active worker.');
    // scheduler = startReminderScheduler(runWorker ?? (() => runReminderWorker(phpBinary)));
    let shutdown = null;

    return {
        server,
        /** Closes Vite and the timer once, without killing an in-flight PHP worker. */
        stop() {
            if (!shutdown) {
                stopping = true;
                console.log('[dev] Stopping Vite and reminders; waiting for any active delivery to finish.');
                shutdown = Promise.all([scheduler.stop(), server.close()]);
            }
            return shutdown;
        },
    };
}

/** Owns the terminal lifecycle while keeping imports safe for isolated tests. */
async function main() {
    if (process.argv.length > 2) {
        throw new Error('For Vite CLI flags, use npm run dev:vite -- <flags>.');
    }
    const development = await startDevelopment();
    /** Handles repeated stop signals without abandoning a delivery midway through sending. */
    function stopDevelopment() {
        development.stop().catch(() => {
            console.error('[dev] Shutdown failed. Check for a remaining local development process.');
            process.exitCode = 1;
        });
    }
    process.on('SIGINT', stopDevelopment);
    // Run before Vite's built-in SIGTERM/stdin handlers, which close the server and then exit Node.
    process.prependListener('SIGTERM', stopDevelopment);
    process.stdin.prependListener('end', stopDevelopment);
}

if (process.argv[1] && pathToFileURL(resolve(process.argv[1])).href === import.meta.url) {
    main().catch(error => {
        console.error('[dev] ' + error.message);
        process.exitCode = 1;
    });
}
