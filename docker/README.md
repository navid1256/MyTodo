# MyTodo web and scheduler images

The default image runs the PHP website only. It does not start MySQL, send
notifications, run migrations, or enable either scheduled job. Select the scheduler
variant explicitly for a separate always-on service, as described below.

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
- Keep the web service, MySQL, and scheduler in the same region.

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

To create a separate production key pair locally, run once from the project root:

```powershell
php process/generate-vapid-keys.php --production
```

The CLI saves `publicKey` and `privateKey` in the gitignored
`storage/private/vapid.production.json`, without printing either key or replacing
existing files. The default command without `--production` still writes
`storage/private/vapid.json` for development. On Windows, when `OPENSSL_CONF` is
unset, the CLI checks PHP's `extras/ssl/openssl.cnf` and XAMPP's
`apache/conf/openssl.cnf`. An explicitly configured `OPENSSL_CONF` is preserved.
If generation fails, check that it points to a readable OpenSSL configuration.

Copy the production values into Railway's `VAPID_PUBLIC_KEY` and
`VAPID_PRIVATE_KEY` runtime variables, and set a valid `VAPID_SUBJECT`. Do not
commit, upload, or put this JSON file under `public/`. The default application
key-file path is not changed by `--production`; production uses the runtime
variables. Scheduler services must reuse the same pair.

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

## Railway scheduler: two jobs in one separate service

Railway's native Cron currently requires at least five minutes between runs and
does not guarantee minute-precise execution. The one-minute reminder requirement
therefore uses an always-on Supercronic service, not Railway's Cron Schedule field.
Supercronic inherits runtime environment variables, logs job output/results, avoids
overlapping the same job, and waits for active jobs when stopped gracefully.

The versioned `docker/scheduler.crontab` contains exactly these UTC schedules:

| Job | Schedule | Command |
| --- | --- | --- |
| Dispatch due reminders | Every minute (`* * * * *`) | `php process/send-reminders.php --send` |
| Extend recurring tasks | Daily at 00:00 UTC (`0 0 * * *`) | `php process/generate-repeat-tasks.php` |

Midnight UTC is 03:30 in Tehran at the current UTC+03:30 offset. This is a
scheduler timezone, not a replacement for each user's task timezone. Repeat
generation keeps the existing 30-day horizon and existing per-run batch limits;
it does not generate only once every 30 days. A bounded run can leave backlog for
the next run, so monitor generated counts and remaining work before increasing load.
Neither job is run immediately on container startup, and missed runs are not
replayed by Supercronic. Recurrence catches up according to the existing generator.

### Configure after committing and pushing the scheduler files

Do not replace or duplicate the existing web service's settings. Create one new
service named `MyTodo Scheduler`, using the same repository, branch, root Dockerfile,
environment and region as MyTodo and MySQL. It is a background service with no domain.

1. In the **scheduler service only**, set `MYTODO_IMAGE_VARIANT=scheduler`.
   Railway passes this declared Docker build argument. Leave it unset (default
   `web`) on the web service. The last Docker stage selects the matching runtime;
   the scheduler has no Apache startup command or inherited HTTP healthcheck.
2. Provide the same `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=UTC`,
   `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
   `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, and `VAPID_SUBJECT` runtime values as
   the web service. Use Railway reference/shared variables rather than committing
   secrets. Keep private MySQL networking; do not copy localhost/XAMPP paths.
3. Set `REMINDER_WORKERS=4` and `REMINDER_RUNTIME_SECONDS=45`. Keep **one replica**,
   **Serverless disabled**, **Cron Schedule empty**, and **Healthcheck Path empty**.
   Choose the available restart policy deliberately (for example On Failure with
   retries); monitor failures because retries can be exhausted. Do not configure
   intentional deployment overlap (`RAILWAY_DEPLOYMENT_OVERLAP_SECONDS=0`). Set
   `RAILWAY_DEPLOYMENT_DRAINING_SECONDS=90` to give active jobs time to finish
   after SIGTERM; monitor long repeat runs because even this grace period is finite.
   Only one scheduler service should be configured. Brief handover concurrency is
   still possible; the existing MySQL worker locks and repeat row locks remain
   necessary and are not replaced by the replica count.
4. For a safe initial deployment, set Custom Start Command to
   `/usr/local/bin/mytodo-scheduler --check`, keep `MYTODO_SCHEDULER_ENABLED` unset,
   and verify `crontab is valid` in the logs. This command exits successfully and
   **does not prove database access or real delivery**. Check both keys and DB
   configuration before activation. A scheduler variant build must appear in the
   Build Logs; an old web image does not contain this executable.
5. When ready to process the **whole due queue**, set
   `MYTODO_SCHEDULER_ENABLED=true` and remove the validation Custom Start Command
   to use the scheduler image's default command. Deploy these changes together.
   This enables both jobs and can send overdue reminders or create recurring tasks;
   it is not the earlier single-reminder targeted test. No database migration runs.

Activation is explicit: without exactly `MYTODO_SCHEDULER_ENABLED=true`, normal
scheduler startup refuses to run. Do not set that flag on the web service. On
Railway, confirm that the Docker build receives `MYTODO_IMAGE_VARIANT`; if the
build still selects `web`, do not activate or add the native one-minute Cron.

### Verify and stop

After activation, observe at least two consecutive UTC-minute executions with
`job succeeded` and the reminder summary. Check one controlled real reminder and
its stored delivery result, then confirm browser receipt separately. A successful
job with zero reminders proves execution only, not Web Push delivery. Check the
daily generator at its scheduled time, or run it manually only with approval of
the resulting production writes. Watch failures, overdue queue growth, `unknown`
deliveries and runs longer than a minute; default overlap prevention can delay the
next run. The existing MySQL launcher/worker locks remain in use across processes.

To stop delivery/generation, stop the scheduler service or remove the activation
flag and redeploy; let an in-flight run finish. Keep the web service and database
running. This does not retract notifications already accepted or undo tasks already
generated. Do not run a second native Cron alongside these schedules. Neither a
minute trigger nor the 45-second worker budget guarantees a browser displays an
alert at an exact second under network delay, downtime or insufficient capacity.

### Isolated local checks

```powershell
docker build --build-arg MYTODO_IMAGE_VARIANT=scheduler --tag mytodo-scheduler:dev .
docker run --rm --network none mytodo-scheduler:dev /usr/local/bin/mytodo-scheduler --check
docker run --rm --network none --mount "type=bind,source=$PWD/tests,target=/var/www/html/tests,readonly" mytodo-scheduler:dev php tests/Backend/docker-scheduler.smoke.php
php tests/Backend/docker-scheduler.test.php
```

These checks validate the real crontab, non-root permissions, activation guard,
and two accelerated harmless fixture jobs with inherited environment. They do
not connect to MySQL, send Push, or prove a production schedule. Supercronic
v0.2.50 downloads are pinned and SHA-256 verified for amd64/arm64; only amd64 was
executed locally. The scheduler reuses the immutable web runtime to avoid divergent
PHP dependencies; Apache files are present but Apache is not started. There is no
public scheduler endpoint or need for a public port. Both variants run as UID/GID 33.

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
- No Docker Compose file is required. Scheduler files and local verification do
  not create, deploy, or activate a Railway service automatically.

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
- [Railway Cron frequency and timing limitations](https://docs.railway.com/cron-jobs#frequency)
- [Supercronic: environment, overlap prevention and shutdown](https://github.com/aptible/supercronic)
- [Pinned Supercronic release](https://github.com/aptible/supercronic/releases/tag/v0.2.50)
- [Railway deployment overlap and graceful draining](https://docs.railway.com/deployments/deployment-teardown)
