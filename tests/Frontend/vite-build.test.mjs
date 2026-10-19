import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import configureVite from '../../vite.config.js';

test('production builds do not depend on local HTTPS certificates or a development server', () => {
    const configuration = configureVite({ command: 'build', mode: 'production' });

    assert.equal(Object.hasOwn(configuration, 'server'), false);
    assert.equal(configuration.root, 'public');
    assert.equal(configuration.publicDir, false);
    assert.equal(configuration.base, '/build/');
    assert.equal(configuration.build.outDir, 'build');
    assert.equal(configuration.build.manifest, 'manifest.json');
});

test('the project exposes a reproducible Vite build command', async () => {
    const packageConfiguration = JSON.parse(await readFile(new URL('../../package.json', import.meta.url), 'utf8'));
    assert.equal(packageConfiguration.scripts.build, 'vite build');
});
