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
   git clone <(https://github.com/navid1256/7Todo.git)>
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
   - Point your web server to the project directory
   - Access via `http://localhost/7Todo` (or your configured URL)

## 📝 Usage

1. **Access the application** - Navigate to the application URL in your browser
2. **Create an account** - Register or login
3. **Add tasks** - Click "Add Todo" and enter your task
4. **Manage tasks** - Edit, mark complete, or delete tasks as needed
5. **View tasks** - Tasks are displayed with timestamps and status

## 📁 Project Structure

```
7Todo/
├── assets/           # Frontend assets (CSS, JS, images)
├── views/            # PHP templates and views
├── libs/             # Library code and utilities
├── bootstrap/        # Application initialization
├── vendor/           # Composer dependencies
├── index.php         # Main entry point
├── auth.php          # Authentication logic
├── composer.json     # Project metadata and dependencies
└── README.md         # This file
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
