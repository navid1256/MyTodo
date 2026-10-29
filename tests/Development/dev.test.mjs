import assert from 'node:assert/strict';
import test from 'node:test';
import { EventEmitter } from 'node:events';
import { PassThrough } from 'node:stream';
import { startReminderScheduler, runReminderWorker, startDevelopment } from '../../scripts/dev.mjs';

/** Flushes promise callbacks without advancing mocked minute timers. */
async function settle() {
    await new Promise(resolve => setImmediate(resolve));
}

test('scheduler waits for the next minute and keeps calendar-minute alignment', async context => {
    context.mock.timers.enable({ apis: ['Date', 'setTimeout'], now: 15_000 });
    const starts = [];
    const scheduler = startReminderScheduler(async () => { starts.push(Date.now()); }, () => {});
    context.mock.timers.tick(44_999);
    await settle();
    assert.deepEqual(starts, []);
    context.mock.timers.tick(1);
    await settle();
    context.mock.timers.tick(60_000);
    await settle();
    assert.deepEqual(starts, [60_000, 120_000]);
    await scheduler.stop();
});

test('long workers never overlap and stopping waits for the active send to finish', async context => {
    context.mock.timers.enable({ apis: ['Date', 'setTimeout'], now: 0 });
    let finish;
    let starts = 0;
    const scheduler = startReminderScheduler(() => {
        starts++;
        return new Promise(resolve => { finish = resolve; });
    }, () => {});
    context.mock.timers.tick(60_000);
    await settle();
    context.mock.timers.tick(60_000);
    await settle();
    assert.equal(starts, 1);
    let stopped = false;
    const stopping = scheduler.stop().then(() => { stopped = true; });
    await settle();
    assert.equal(stopped, false);
    finish();
    await stopping;
    context.mock.timers.tick(120_000);
    await settle();
    assert.equal(starts, 1);
});

test('a failed worker reports a safe error and the next minute still runs', async context => {
    context.mock.timers.enable({ apis: ['Date', 'setTimeout'], now: 0 });
    const logs = [];
    let starts = 0;
    const scheduler = startReminderScheduler(async () => {
        starts++;
        throw new Error('private provider details');
    }, text => logs.push(text));
    context.mock.timers.tick(60_000);
    await settle();
    context.mock.timers.tick(60_000);
    await settle();
    assert.equal(starts, 2);
    assert.ok(logs.some(text => text.includes('failed')));
    assert.ok(logs.every(text => !text.includes('private provider details')));
    await scheduler.stop();
});

test('stopping before the first minute prevents any worker from starting', async context => {
    context.mock.timers.enable({ apis: ['Date', 'setTimeout'], now: 59_999 });
    let starts = 0;
    const scheduler = startReminderScheduler(async () => { starts++; }, () => {});
    await scheduler.stop();
    await scheduler.stop();
    context.mock.timers.tick(120_000);
    await settle();
    assert.equal(starts, 0);
});

test('PHP uses shell-free arguments, hidden detached process, and retained output pipes', async () => {
    const child = new EventEmitter();
    child.stdout = new PassThrough();
    child.stderr = new PassThrough();
    let launched;
    const result = runReminderWorker('C:/PHP With Spaces/php.exe', (command, args, options) => {
        launched = { command, args, options };
        return child;
    });
    assert.equal(launched.command, 'C:/PHP With Spaces/php.exe');
    assert.equal(launched.args.at(-1), '--send');
    assert.match(launched.args[0], /send-reminders\.php$/);
    assert.equal(launched.options.shell, false);
    assert.equal(launched.options.windowsHide, true);
    assert.equal(launched.options.detached, true);
    assert.deepEqual(launched.options.stdio, ['ignore', 'pipe', 'pipe']);
    child.emit('close', 0, null);
    await result;
});

test('worker startup errors and unsuccessful exits are rejected without provider details', async () => {
    for (const event of ['error', 'close']) {
        const child = new EventEmitter();
        child.stdout = new PassThrough();
        child.stderr = new PassThrough();
        const result = runReminderWorker('missing-php', () => child);
        const rejected = assert.rejects(result, /Reminder worker/);
        if (event === 'error') child.emit('error', new Error('private details'));
        else child.emit('close', 1, null);
        await rejected;
    }
});

test('production environment is rejected before Vite or reminders start', async () => {
    let started = false;
    await assert.rejects(startDevelopment({
        environment: { APP_ENV: 'production' },
        createViteServer: async () => { started = true; },
    }), /development only/i);
    assert.equal(started, false);
});

test('Vite startup failure closes its resources without scheduling reminders', async () => {
    let closed = false;
    let sent = false;
    await assert.rejects(startDevelopment({
        environment: { APP_ENV: 'local', MYTODO_PHP_BINARY: process.execPath },
        createViteServer: async () => ({
            async listen() { throw new Error('Port already in use'); },
            async close() { closed = true; },
        }),
        runWorker: async () => { sent = true; },
    }), /Port already in use/);
    assert.equal(closed, true);
    assert.equal(sent, false);
});

test('Vite own shutdown path waits for an active worker instead of exiting during delivery', async context => {
    context.mock.timers.enable({ apis: ['Date', 'setTimeout'], now: 0 });
    context.mock.method(console, 'log', () => {});
    let finish;
    let closed = false;
    const development = await startDevelopment({
        environment: { APP_ENV: 'local', MYTODO_PHP_BINARY: process.execPath },
        createViteServer: async config => {
            const server = {
                async listen() {},
                async close() { closed = true; },
                printUrls() {},
            };
            config.plugins[0].configureServer(server);
            return server;
        },
        runWorker: () => new Promise(resolve => { finish = resolve; }),
    });
    context.mock.timers.tick(60_000);
    await settle();
    const stopping = development.stop();
    const viteExit = development.server.close();
    await settle();
    assert.equal(closed, false);
    finish();
    await Promise.all([stopping, viteExit]);
    assert.equal(closed, true);
});
