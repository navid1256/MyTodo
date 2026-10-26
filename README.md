# 7Todo

A simple and interactive Todo list application built with PHP, MySQL, and AJAX.

## 📋 Description

7Todo is a lightweight todo management system designed for learning and personal productivity. It features dynamic AJAX interactions for seamless task management with support for Persian calendar functionality.

## ✨ Features

- **Add, edit, and delete todos** - Manage your tasks easily
- **AJAX-based interface** - Fast, responsive interactions without page reloads
- **MySQL database** - Persistent storage of your tasks
- **User authentication** - Secure login system
- **Persian calendar support** - Built-in Verta library for Jalali date support

## 🛠️ Tech Stack

- **Backend**: PHP
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript (AJAX)
- **Dependencies**:
  - Verta (^2.1) - For Persian/Jalali calendar support

## 📦 Requirements

- PHP 7.0 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server with PHP support
- Composer for dependency management

## 🚀 Installation

1. **Clone the repository**

   ```bash
   git clone https://github.com/navid1256/7Todo.git
   cd 7Todo
   ```

2. **Install dependencies**

   ```bash
   composer install
   ```

3. **Configure database**
   - Update database credentials in your configuration file
   - Create the MySQL database and import the schema

4. **Set up web server**
   - Point the web server document root to the `public/` directory
   - Access the application through the configured host or local development URL

## 📝 Usage

For the Railway-compatible Docker image, build instructions, runtime variables,
and persistence requirements, see [docker/README.md](docker/README.md).

1. **Access the application** - Navigate to the application URL in your browser
2. **Create an account** - Register or login
3. **Add tasks** - Click "Add Todo" and enter your task
4. **Manage tasks** - Edit, mark complete, or delete tasks as needed
5. **View tasks** - Tasks are displayed with timestamps and status

## 📁 Project Structure

```
7Todo/
├── App/              # Controllers, services, repositories, HTTP, and domain helpers
├── config/           # Application and database configuration
├── Database/         # Database schema and migrations
├── public/           # Web root, front controller, assets, and public uploads
│   └── index.php     # Application front controller
├── routes/           # Web and API route definitions
├── tests/            # Automated tests
├── views/            # Layouts, pages, components, and modals
├── vendor/           # Composer dependencies
├── composer.json     # Project metadata and dependencies
└── README.md         # This file
```

## Iranian calendar holiday colors

The four interactive calendars highlight Iranian public holidays and Fridays without displaying occasion titles. Holiday colors do not disable dates or change Task, Repeat, or Reminder scheduling.

- Source: [Persian-Calendar API](https://www.persian-calendar.com/developers). No API key or additional package is needed.
- Local endpoint: authenticated `GET /api/calendar/holidays?year=1405`, with a Jalali year from 1347 to 1500. It returns Gregorian `dates`, `year`, `available`, `stale`, and `refresh_after` (seconds), never event titles. Invalid input returns 422; unauthenticated requests return 401. An upstream outage returns a normal date-only result with availability/staleness flags so calendar rendering continues.
- Shared file cache: `storage/cache/calendar-holidays/`, outside the public document root and Git. PHP needs write access. Refresh is on demand, not a new Cron job: the default freshness is 24 hours, shortened by the provider's `Cache-Control` and `Age`. `no-store`, `no-cache`, and `Retry-After` are honored. On a refresh failure, a previous successful date snapshot remains available as stale data.
- HTTP requests use the existing Symfony client with TLS verification, private-network protection, no redirects, a five-second duration limit, and a response-size limit. Jalali and Gregorian dates must agree before a new snapshot replaces the previous one.
- Styles: `public/assets/css/calendar-holidays.css`. All four calendar renderers use the same holiday decorator and share pending requests by Jalali year, including Gregorian months spanning Nowruz.

Focused checks:

```bash
php vendor/bin/phpunit tests/Backend/CalendarHolidayServiceTest.php
node --test tests/Frontend/calendar-holidays.test.mjs
```

## Calendar-aware date text

Home task metadata, Activity headings/times, Messages, and Sent Notifications use `App/Helpers/CalendarDateFormatter.php`. Manage Tasks shares the same task-row formatter. Saved Calendar System and IANA timezone settings control display; the interface language does not change the selected calendar.

- Jalali dates use Persian month names, digits, and AM/PM labels, even in the English interface. Date text uses the existing self-hosted Vazirmatn font. Gregorian formatting stays unchanged.
- Activity retains translated Today/Yesterday headings. Its grouping keys and `<time datetime>` attributes remain Gregorian/ISO; form values and stored UTC timestamps are not converted to display strings.
- Reminder edit responses include a calendar-aware `formatted_remind_at` alongside the canonical UTC `remind_at`, so AJAX updates match the page's date display.
- `CalendarDateFormatterTest` covers both languages/calendars, timezone day boundaries, leap days, escaping, and canonical attributes. `NotificationDateDisplayTest` checks the real controller response with an in-memory SQLite fixture; it does not verify MySQL locking or access the application's database.

```bash
php vendor/bin/phpunit tests/Backend/CalendarDateFormatterTest.php
php vendor/bin/phpunit tests/Backend/NotificationDateDisplayTest.php
```

## 👤 Author

Navid Ahmadzadeh

- Email: <navid.syndicate@gmail.com>

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

Contributions are welcome! Feel free to open issues and submit pull requests.

---

Built with ❤️ for learning and productivity
