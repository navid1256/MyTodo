import { existsSync, readFileSync } from 'node:fs';
import { defineConfig, loadEnv } from 'vite';

const DEV_HOST = 'mytodo.php';
const DEV_PORT = 5173;
const DEV_ORIGIN = `https://${DEV_HOST}:${DEV_PORT}`;
const BACKEND_ORIGIN = `https://${DEV_HOST}`;

export default defineConfig(({ mode }) => {
    const environment = loadEnv(mode, process.cwd(), 'MYTODO_');
    const certificatePath = environment.MYTODO_VITE_CERT_PATH;
    const privateKeyPath = environment.MYTODO_VITE_KEY_PATH;

    if (!certificatePath || !privateKeyPath) {
        throw new Error('Vite HTTPS certificate paths are not configured in .env.local.');
    }

    if (!existsSync(certificatePath) || !existsSync(privateKeyPath)) {
        throw new Error('The configured Vite HTTPS certificate or private key does not exist.');
    }

    return {
        root: 'public',
        server: {
            host: DEV_HOST,
            port: DEV_PORT,
            strictPort: true,
            origin: DEV_ORIGIN,
            https: {
                cert: readFileSync(certificatePath),
                key: readFileSync(privateKeyPath),
            },
            cors: {
                origin: BACKEND_ORIGIN,
            },
        },
    };
});
