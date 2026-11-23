# Library Management System

A web-based Library Management System built using PHP, MySQL, HTML, CSS and JavaScript.

## Features

- Dashboard with live library statistics
- Book management (Add, Search and View)
- Member management
- Book issue and return management
- Automatic book stock management using MySQL triggers
- Fine calculation (₹2 per overdue day)
- Staff authentication (Admin and Librarian)
- Secure password hashing
- Session-based authentication and logout
- Shared sidebar navigation

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **Server:** Apache
- **Environment:** Kubuntu Linux

## Project Structure

```text
library-management/
├── admin/
├── assets/
├── auth/
├── config/
├── database/
├── includes/
├── index.php
├── books.php
├── members.php
├── issues.php
├── returns.php
├── fines.php
├── database.sql
└── README.md
```

## Installation

1. Clone this repository.
2. Place the project inside your Apache web root.
3. Create a MySQL database named `sql_project`.
4. Import `database.sql`.
5. Copy `config/database.example.php` to `config/database.php`.
6. Configure your MySQL credentials.
7. Open the project through your local Apache server.

## Author

**Rishi kumar pathak**

GitHub: [@sudorishi11](https://github.com/sudorishi11)

---

This project was developed as a practical Database Management System project to explore PHP, MySQL, database relationships, triggers and web development.

