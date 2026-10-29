# MyTodo

A personal task manager built with PHP, MySQL, and JavaScript. MyTodo combines recurring tasks, browser push reminders, Gregorian and Jalali calendars, and an English/Persian dashboard in a custom MVC application.

**Live application:** [mytodo-production-1.up.railway.app](https://mytodo-production-1.up.railway.app/)

[Source code](https://github.com/navid1256/MyTodo) · [Report an issue](https://github.com/navid1256/MyTodo/issues)

## Features

- **Task management:** create dated or undated tasks, mark them complete, undo completion, and delete tasks with AJAX.
- **Dashboard views:** Home, Activity with completion-date filters, Manage Tasks with a calendar, and a dedicated Recurring Tasks page.
- **Recurring schedules:** daily, weekly, monthly, and custom rules; interval and end conditions; month-end handling; Pause, Resume, and Cancel; editing one occurrence or that occurrence and future tasks.
- **Browser reminders:** configurable reminder offsets, per-browser subscriptions, Enable/Disable controls, and a background Web Push delivery worker.
- **Notification management:** Messages manages reminders; Sent Notifications is the archive of reminders accepted by a push provider. Messages is not a chat system.
- **Calendar and language preferences:** Gregorian/Jalali calendars, English/Persian dashboard translations, RTL layouts, and a saved IANA timezone per user.
- **Account controls:** registration, login, profile and avatar updates, password changes, and account settings saved explicitly with a Save button.
- **Light and dark themes**, with self-hosted Vazirmatn for Persian date text.

MyTodo is under active development. A deployed web application does not imply that every roadmap item, mobile browser, or PWA installation flow is complete.

## Technology

| Area | Implementation |
| --- | --- |
| Backend | PHP, custom MVC, Services and Repositories, PDO |
| Database | MySQL/MariaDB, InnoDB, `utf8mb4` |
| Frontend | Server-rendered PHP views, HTML/CSS, JavaScript ES modules, Fetch/AJAX |
| Development assets | Vite |
| Calendars | Verta on the server, `jalaali-js` and `Intl.DateTimeFormat` in the browser |
| Web Push | `minishlink/web-push`, VAPID, Service Worker |
| Supporting libraries | Symfony HTTP components, Nyholm PSR-7, PHP dotenv |
| Tests | PHPUnit, standalone PHP checks, Node.js test runner |

The application does not use the full Laravel or Symfony framework. Individual components are used where needed.

## Requirements

- **PHP 8.2 or newer**, with PDO MySQL, cURL, OpenSSL, mbstring, fileinfo, and GD available. Composer checks additional dependency requirements.
- **Composer 2**.
- **Node.js 22.12+** for development. The locked Vite version also supports Node.js `^20.19.0`; its complete engine requirement is `^20.19.0 || >=22.12.0`.
- **MySQL or MariaDB** with InnoDB and `utf8mb4` support. The schema export identifies MariaDB 10.4.32; it is not a guarantee of support for every database version.
- **Apache with `mod_rewrite` and SSL**, or an equivalent web-server configuration that serves `public/` and routes application requests to `public/index.php`.
- A trusted **HTTPS** origin for browser push and the supplied Vite development configuration.
- For tests: PDO SQLite and the XML/DOM extensions required by PHPUnit. MySQL-specific integration tests also need a development database.
- For the reminder worker pool: PHP CLI with `proc_open` available.

Check your PHP installation without modifying the database:

```bash
composer check-platform-reqs --lock
```

## Local setup

The current development configuration uses **`https://mytodo.php`** for PHP and **`https://mytodo.php:5180`** for Vite. XAMPP is suitable for the PHP/Apache/MySQL side; Vite runs separately.

### 1. Clone and install dependencies

```bash
git clone https://github.com/navid1256/MyTodo.git
cd MyTodo
composer install
npm ci
```

Keep `composer.lock` and `package-lock.json` in Git. Do not commit `vendor/` or `node_modules/`.

### 2. Configure the environment

For a new checkout, copy `.env.example` to `.env`. Do not overwrite an existing environment file.

```powershell
Copy-Item .env.example .env
```

Edit the following values for your own local database:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=UTC

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mytodo
DB_USERNAME=mytodo_user
DB_PASSWORD=replace-with-your-local-password
```

Keep application/storage time in UTC. The user's timezone is a separate account setting and controls date entry and display.

Server environment variables take precedence over file values. The PHP loader reads `.env`; Vite can read both `.env` and `.env.local`. Never put passwords or private keys in frontend `VITE_*` variables.

### 3. Create a fresh database

Create an empty database named `mytodo` with `utf8mb4_unicode_ci`, then import [Database/mytodo.sql](Database/mytodo.sql) using phpMyAdmin or your MySQL client. Grant the configured application user access to that database.

For a **fresh installation**, the schema includes the settings, repeat-rule, push-subscription, and reminder-delivery tables. Do not replay all historical migrations after importing it.

For an **existing installation**, back up the database and inspect its schema before applying only the required files from `Database/migrations/`. There is no documented automatic migration runner; importing the full schema into an existing database is not an upgrade procedure.

### 4. Configure local HTTPS

Add this entry to the operating system's hosts file, using administrator privileges where required:

```text
127.0.0.1 mytodo.php
```

Use a trusted local certificate, for example one generated with `mkcert`. Run the following from the project root after installing that tool:

```powershell
mkcert -install
New-Item -ItemType Directory -Force storage/private/tls
mkcert -cert-file storage/private/tls/mytodo.php.crt -key-file storage/private/tls/mytodo.php.key mytodo.php
```

Configure an Apache HTTPS virtual host for `mytodo.php` with:

- Document root and directory access pointing to **`MyTodo/public`**, not the repository root.
- The certificate and private key generated above.
- `AllowOverride All` so `public/.htaccess` can route requests.
- SSL and rewrite modules enabled.

Restart Apache after changing its configuration. Do not expose `.env`, `vendor/`, `Database/`, or private storage through the document root.

Create `.env.local` in the project root with the paths Vite should read:

```dotenv
MYTODO_VITE_CERT_PATH=storage/private/tls/mytodo.php.crt
MYTODO_VITE_KEY_PATH=storage/private/tls/mytodo.php.key
```

These paths are relative to the project root when `npm run dev` is started there. Absolute paths are also supported. Existing trusted certificate files can be used instead; do not regenerate them unnecessarily.

### 5. Start the application

Start Apache and MySQL, then run Vite from the project root:

```bash
npm run dev
```

Open **[https://mytodo.php/](https://mytodo.php/)** and create an account. Keep Vite running while developing. Its address on port `5180` serves assets and HMR; it is not the PHP application's home page.

PHP needs write access to `storage/logs/`, `storage/cache/`, private key storage when generating VAPID keys, and `public/storage/avatars/` for uploads. Give only the required account access; do not solve permission problems with unrestricted production permissions.

## Everyday use

1. Create a task with **Add New Task**; add a date, time, reminders, or a repeat rule as needed.
2. Use **Manage Tasks** to browse tasks by date, and **Activity** to review completed work.
3. Manage recurring series from **Recurring Tasks**. Recurrence is independent of completing earlier occurrences.
4. Use **Messages** to manage reminders and the header bell to open **Sent Notifications**.
5. Save language, calendar, and timezone preferences in **Account Settings**. Browser notification controls are independent of the settings Save button.

### Sent notification archive

The bell counts **unread sent** notifications, not all reminders. Opening Sent Notifications marks the current user's existing sent archive read through a CSRF-protected POST. Later notifications remain unread until the archive is opened again.

The archive starts with 20 notifications and **Load More** retrieves older pages using a stable send-time/ID cursor. **Clear Notifications** asks for confirmation and clears the user's entire sent archive, including unloaded pages. It sets `task_reminders.cleared_at`; it does not delete tasks, scheduled reminders, browser subscriptions, or delivery deduplication history. Clearing the archive does not dismiss notifications already displayed by the operating system.

Existing installations need [20261029_add_notification_archive_state.sql](Database/migrations/20261029_add_notification_archive_state.sql), applied once after a backup. Fresh schema imports already include these columns. The [rollback](Database/migrations/rollbacks/20261029_add_notification_archive_state.sql) removes the read/clear metadata and makes previously cleared sent reminders visible again; it does not restore a previous read state.

Archive APIs require authentication: `GET /api/notifications/sent?cursor=...` returns `items` and `next_cursor` (null on the last page); `GET /api/notifications/unread` returns `unread_count`. `POST /api/notifications/read` and `POST /api/notifications/clear` require `csrf_token`; Clear also returns `cleared_count`. These actions affect only the session user's sent reminders. Invalid cursors return 422, invalid CSRF returns 403, and unauthenticated AJAX requests return 401.

## Browser push and background jobs

### Configure VAPID

Generate a key pair once for your installation:

```bash
php process/generate-vapid-keys.php
```

The command creates `storage/private/vapid.json`, never prints the private key, and refuses to overwrite an existing key file. Keep this file private, back it up securely, and preserve the keys across deployments; changing them can require browser re-subscription.

Set a valid contact subject in the server environment or `.env`:

```dotenv
VAPID_SUBJECT=mailto:admin@example.com
```

Replace the example address with a real contact. This is VAPID sender identification, **not email notification delivery**.

Leave `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` empty to use the default key file. Alternatively, provide both directly through server secrets, or set `VAPID_KEYS_FILE` to another private path outside the web root.

On Windows/XAMPP, if OpenSSL cannot generate keys, point `OPENSSL_CONF` at the OpenSSL configuration for your installation before running the generator:

```powershell
$env:OPENSSL_CONF = 'C:\xampp\apache\conf\openssl.cnf'
```

In the browser, open Account Settings and explicitly choose **Enable Notifications**, then allow the permission prompt. Disable removes the current browser's subscription; it does not revoke Chrome's site permission or disable other registered browsers.

### Run jobs separately from web requests

The Service Worker displays received pushes and handles clicks. It does not read reminder schedules from MySQL or replace server-side scheduling.

| Job | Entry point | Intended schedule |
| --- | --- | --- |
| Deliver due reminders | `process/send-reminders.php --send` | Every minute |
| Extend recurring schedules | `process/generate-repeat-tasks.php` | Daily, maintaining a 30-day lookahead |

Inspect the reminder CLI safely before enabling delivery:

```bash
php process/send-reminders.php --help
```

**Warning:** running `php process/send-reminders.php --send` sends real pushes for eligible reminders across the configured database. Recurrence generation also writes tasks and reminders. Do not run either job against a production database as a casual smoke test.

For a controlled single-reminder test, the CLI supports paired `--reminder-id` and `--subscription-id` options. Select and verify both IDs and their common owner before use. This mode processes at most that pair, without draining the shared queue.

Worker settings are optional; the defaults are:

```dotenv
REMINDER_WORKERS=4
REMINDER_RUNTIME_SECONDS=45
```

The accepted ranges are 1–16 workers and a 1–50 second reservation window. Database advisory locks and delivery claim tokens coordinate overlapping workers. Temporary provider failures have bounded retries; ambiguous outcomes are not automatically retried. A `sent` record means provider acceptance, not guaranteed device display or reading.

Configure the jobs with your server's scheduler: for example Cron on Linux or Task Scheduler on Windows. On container platforms, use a separate worker/scheduler service with the same database and VAPID secrets. Scheduling and browser delivery must be tested independently of the web deployment; a one-minute schedule is not an exact-second delivery guarantee.

## Calendars, localization, and holidays

### Calendar-aware date text

Saved Calendar System and IANA timezone settings control display independently of the interface language. Home task metadata, Activity headings/times, Messages, and Sent Notifications use `App/Helpers/CalendarDateFormatter.php`; Manage Tasks shares the task-row formatter.

- Jalali month/day names, digits, and AM/PM labels remain Persian even in the English interface. Date text uses self-hosted Vazirmatn.
- Switching Gregorian/Jalali preserves the actual selected day rather than replacing it with a different date.
- Activity keeps translated Today/Yesterday headings. Grouping keys, canonical form dates, and `<time datetime>` attributes remain Gregorian/ISO; database timestamps remain UTC.
- Reminder edits return `formatted_remind_at` for display alongside the canonical UTC `remind_at` value.
- Language `Default` follows a supported browser language, with English fallback. Background workers have no browser language header, so their default-language fallback is English.

### Iranian holiday colors

The four interactive calendars highlight Iranian public holidays and Fridays without showing occasion titles. Holiday styling does not disable dates or change task, repeat, or reminder schedules.

- Source: [Persian-Calendar API](https://www.persian-calendar.com/developers); no API key or additional package is needed.
- Authenticated endpoint: `GET /api/calendar/holidays?year=1405`, accepting Jalali years 1347–1500. It returns Gregorian `dates`, `year`, `available`, `stale`, and `refresh_after` in seconds, never occasion titles. Invalid input returns 422; unauthenticated requests return 401.
- Shared cache: `storage/cache/calendar-holidays/`, outside the web root and Git. Default freshness is 24 hours, shortened by provider `Cache-Control` and `Age`. `no-store`, `no-cache`, and `Retry-After` are respected. Refresh is on demand, not a separate scheduled job; previous successful dates can be returned as stale data when refresh fails.
- HTTP requests use TLS verification, private-network protection, no redirects, a five-second duration limit, and a response-size limit. Jalali and Gregorian dates must agree before replacing a snapshot. Upstream failure does not prevent calendar rendering.
- Styles live in `public/assets/css/calendar-holidays.css`. Calendar renderers share a holiday decorator and pending requests by Jalali year, including Gregorian months spanning Nowruz.

## Tests

Run commands from the repository root. There are two PHP test styles: PHPUnit discovers `*Test.php` through `phpunit.xml`; standalone `*.test.php` checks must be run explicitly.

```bash
# PHPUnit suite: explicit MySQL integration cases are skipped without opt-in.
php vendor/bin/phpunit

# Browser-independent JavaScript tests.
node --test tests/Frontend/*.test.mjs

# Representative standalone PHP checks; these do not send real pushes.
php tests/Backend/push-subscription.test.php
php tests/Backend/push-api.test.php
php tests/Backend/reminder-worker-arguments.test.php
php tests/Backend/reminder-worker-cli.test.php
```

Focused calendar/date checks:

```bash
php vendor/bin/phpunit tests/Backend/CalendarHolidayServiceTest.php
php vendor/bin/phpunit tests/Backend/CalendarDateFormatterTest.php
php vendor/bin/phpunit tests/Backend/NotificationDateDisplayTest.php
node --test tests/Frontend/calendar-holidays.test.mjs
```

The date tests cover both calendars/languages, timezone boundaries, leap days, escaping, and canonical attributes. Notification date-display tests use in-memory SQLite fixtures; they do not validate MySQL locking.

**Database-test safety:** inspect integration scripts before running them and use only an explicitly approved development/test database. Some use connection-local temporary tables; multi-process tests create and drop uniquely prefixed fixture tables. Repeat integration tests require explicit opt-in. Never run an indiscriminate loop over every PHP file in `tests/Backend/`, and do not treat SQLite or fake-sender results as proof of MySQL concurrency or real browser delivery.

## Architecture

Requests enter through `public/index.php`. Routes select controllers, services apply application rules, repositories own SQL, and views render HTML or dashboard partials. Frontend modules handle navigation, forms, calendars, and AJAX actions.

```text
MyTodo/
├── App/
│   ├── Config/          # Environment loading and feature configuration
│   ├── Controllers/     # HTTP request handling
│   ├── Database/        # PDO connection
│   ├── Exceptions/      # Application exceptions and error handling
│   ├── Helpers/         # Dates, timezones, assets, and shared utilities
│   ├── Http/            # Request, response, router, and sessions
│   ├── Localization/    # Translator
│   ├── Middleware/      # Request guards
│   ├── Models/          # Domain model classes
│   ├── Notifications/   # Sender contracts, outcomes, and Web Push delivery
│   ├── Repositories/    # Persistence and database queries
│   └── Services/        # Task, recurrence, reminder, and account logic
├── config/              # Application/database/worker settings
├── Database/            # Fresh schema and incremental SQL migrations
├── process/             # CLI jobs and VAPID key generation
├── public/              # Web root, assets, Service Worker, uploaded avatars
├── resources/lang/      # English and Persian translation catalogs
├── routes/              # Web and API routes
├── storage/             # Private keys, logs, and caches; not committed
├── tests/               # PHP and JavaScript tests
└── views/               # Layouts, pages, components, and modals
```

## Deployment notes

The live application is hosted on Railway at the link above. This README describes the checked-in source; deployment-specific configuration may differ between branches.

- Serve **only `public/`**, terminate HTTPS correctly, and set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_TIMEZONE=UTC` through server environment variables.
- Provide database and VAPID credentials through private environment variables or private mounted files, never frontend assets or Git.
- Persist uploaded avatars and retain VAPID keys across deployments. Review session storage and database backups rather than relying on an ephemeral container filesystem.
- Configure and verify background scheduling separately. Web-service availability alone does not prove reminder jobs are running.
- The current `package.json` exposes `npm run dev`, not a production build script. Configure and verify production asset delivery for the deployment branch; do not use the Vite development server as a public production asset server.

PWA installation, offline caching, and unread-notification features should not be assumed complete merely because browser Web Push is available.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Vite assets fail with `ERR_CONNECTION_REFUSED` | Run `npm run dev`; verify the configured domain and port `5180`. |
| Vite reports missing HTTPS files | Check both `MYTODO_VITE_*_PATH` values and restart Vite after environment changes. |
| Application routes return 404 | Verify the `public/` document root, rewrite module, and `AllowOverride All`. |
| Login fails on the custom local domain | Use trusted HTTPS; non-loopback HTTP requests are rejected by session setup. |
| Notification controls report blocked permission | Adjust the browser's permission for this origin, reload, and enable notifications explicitly. |
| A reminder stays pending | Verify a valid browser subscription and that the server-side scheduler is actually invoking the CLI. |
| Holidays are temporarily unavailable | Check outbound HTTPS and cache permissions; the calendar should still render. |

## Contributing

Open an [issue](https://github.com/navid1256/MyTodo/issues) or submit a focused pull request with the reason for the change and the checks performed. Keep secrets, uploads, dependency folders, and generated build assets out of commits. Changes to scheduling, ownership, timezones, or recurrence should include relevant tests and preserve canonical UTC/Gregorian storage.

## Author and license

**Navid Ahmadzadeh** — <navid.syndicate@gmail.com>

`composer.json` declares the project license as **MIT**. A standalone `LICENSE` file is not currently included in this checkout.
