# MyTodo web image

This image runs the PHP website only. It does not start MySQL, send notifications,
run migrations, or enable either scheduled job. The scheduler is a separate service.

## Build

```powershell
docker build --pull --tag mytodo-web:dev .
```

BuildKit builds Composer dependencies and Vite assets in separate stages. Local
`.env` files, TLS/VAPID keys, databases, uploads, and development dependencies are
not copied into the runtime image. Only `public/` is served over HTTP.

Dependency installs use Docker's normal layer cache, not BuildKit cache mounts.
Railway requires service-specific IDs for cache mounts; avoiding them keeps this
Dockerfile portable between the web service, future scheduler, and local Docker.
Lockfiles are copied before application code so code-only changes can still reuse
the dependency installation layers.

Official PHP 8.3, Node 24, and Composer images are pinned to verified multi-platform
digests. Review and update the digests regularly, then rebuild and retest for
security updates. Composer and npm dependencies are installed from their lockfiles;
OS package versions are not fully pinned, so the entire build is not bit-for-bit reproducible.
PHP 8.3 avoids the implicit-nullable deprecations emitted by the currently locked
Illuminate dependencies on PHP 8.4; dependency upgrades are outside this change.

## Railway web service

- Source: the GitHub repository and the intended deployment branch.
- Root directory: the repository root, not `public/`.
- Builder: Dockerfile; use the root `Dockerfile`.
- Custom build/start commands: leave empty to use the Dockerfile.
- Cron Schedule: leave empty on the web service.
- Serverless: disabled for the always-on configuration.
- Healthcheck Path: `/healthz` (PHP/web-server liveness only, not database readiness).
- Port: `PORT`, default `8080`; Apache listens on that environment variable.
- Keep the web service, MySQL, and the future scheduler in the same region.

The Apache PHP module uses only `mpm_prefork`. The web stage explicitly disables
`mpm_event` and `mpm_worker`, enables prefork, and generates
`/etc/apache2/mytodo-modules.conf` from the enabled non-MPM modules plus prefork.
This explicit include list replaces both `mods-enabled` load/config wildcards:
even if event/worker symlinks reappear, neither their modules nor their incompatible
configuration is included. Enable any additional module before generating this
list, then rebuild the image; runtime `a2enmod` is intentionally not supported.
Apache configuration is checked during the build, and the runtime smoke test also
verifies that prefork is the only loaded MPM.

The reported Railway build passed `apache2ctl -t`, while the reported runtime had
both event and prefork links. The cause of that filesystem difference is not yet
proven; a cache/export/unpack issue is a hypothesis, not a confirmed diagnosis.
The regression below reproduces the conflicting-link state without depending on
that hypothesis. Local success does not replace checking the deployed image.

If a temporary diagnostic Custom Start Command was added while investigating
`More than one MPM loaded`, remove it before deploying the corrected image so
the Dockerfile's `apache2-foreground` command is used again.

Provide these runtime variables through Railway Variables, never build arguments:

```text
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=UTC
DB_DRIVER=mysql
DB_HOST=<private MySQL host>
DB_PORT=<MySQL port>
DB_DATABASE=<database name>
DB_USERNAME=<database user>
DB_PASSWORD=<database password>
```

When enabling Web Push later, supply the existing VAPID public/private keys and
subject as runtime variables. Do not regenerate the keys on deployment. Never
point a test container at the development or production database without approval.

Apache trusts `X-Forwarded-Proto: https` to preserve secure session cookies behind
Railway's TLS-terminating edge. This configuration assumes Railway is the ingress;
do not reuse that trust rule unchanged behind an untrusted proxy or direct public HTTP.

## Local liveness smoke test

This test deliberately supplies no database credentials and runs no application jobs:

```powershell
docker run --detach --name mytodo-web-smoke --publish 127.0.0.1:8088:8080 mytodo-web:dev
curl.exe --fail http://127.0.0.1:8088/healthz
docker logs mytodo-web-smoke
docker stop mytodo-web-smoke
docker rm mytodo-web-smoke
```

The image runs as `www-data` (UID/GID 33), not root. Code and dependencies remain
root-owned; only upload/log and Apache runtime directories are writable.
Node, npm, Composer, tests, and local secrets are absent from the final image.
The image includes GMP for Web Push cryptography, GD for images, PDO MySQL,
OPcache, and the PHP CLI required by the existing reminder scripts.

The isolated runtime smoke test can also be run without publishing a port or
providing database credentials. It mounts only the test directory read-only:

```powershell
docker run --detach --name mytodo-runtime-check --network none --mount "type=bind,source=$PWD/tests,target=/var/www/html/tests,readonly" mytodo-web:dev
docker exec mytodo-runtime-check php tests/Backend/docker-runtime.smoke.php
docker stop mytodo-runtime-check
docker rm mytodo-runtime-check
```

Run the restored-MPM-link regression in a separate disposable container. Root is
used only to create the test fixture; the web image still runs as `www-data`:

```powershell
docker run --rm --network none --user root --mount "type=bind,source=$PWD/tests,target=/var/www/html/tests,readonly" --entrypoint sh mytodo-web:dev tests/Backend/docker-apache-mpm.regression.sh
```

This test deliberately restores event and worker `.load` and `.conf` symlinks,
then verifies that Apache configuration remains valid and only prefork is loaded.
It does not access MySQL or send notifications.

## Persistent data and release boundaries

- Uploaded avatars live at `/var/www/html/public/storage/avatars`; configure durable
  storage before real use. A mounted volume must permit writes by UID/GID 33.
- Sessions currently live at `/tmp/mytodo-sessions`. They are ephemeral and users
  may need to log in again after replacement/restart; shared sessions are required
  before using multiple replicas. Do not assume a mounted volume solves this automatically.
- Application exceptions currently also write `storage/logs/app.log`; PHP and
  Apache logs go to stdout/stderr. Durable/central application logging is a later step.
- The image intentionally excludes database migration scripts. Apply migrations
  separately with explicit approval; there is no automatic migration on startup.
- No Docker Compose file or production scheduler is installed by this change.

## Asset checks

```powershell
npm run build
node --test tests/Frontend/vite-build.test.mjs
php tests/Backend/vite-assets.test.php
php tests/Backend/vite-layout.test.php production english
php tests/Backend/vite-layout.test.php production persian
php tests/Backend/vite-layout.test.php local english
```

The PHP layout reads `public/build/manifest.json` for hashed JS and font CSS.
`APP_ENV=local` continues using the existing HTTPS Vite development server.

The existing `tests/Backend/recurring-page.test.php` fixture is incomplete: it does
not create `task_repeat_reminder_templates`, so the unmodified Repeat service
fails before rendering the page. This unrelated failure is not fixed or hidden
by the Docker changes. Real-database integration tests and a full logged-in
Railway flow require separate, explicitly authorized verification.

## References

- [Docker build best practices](https://docs.docker.com/build/building/best-practices/)
- [Official PHP image](https://hub.docker.com/_/php)
- [Vite backend integration](https://vite.dev/guide/backend-integration.html)
- [Railway Dockerfiles](https://docs.railway.com/builds/dockerfiles)
