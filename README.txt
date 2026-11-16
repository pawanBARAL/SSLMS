STUDENT SMART LIFE MANAGEMENT SYSTEM (SSLMS)
README - INSTALLATION AND USER GUIDE
===========================================

ABOUT THIS PROJECT
SSLMS is a student planning website built with PHP, MySQL and CSS.
It lets students manage assignments, study priorities, work shifts,
income and expenses, monthly budgets, reminders and calendar events.
It also includes rule-based Study Planner, Budget Advisor, Spending
Prediction and Work-Study Balance Advisor features.

WHAT YOU NEED
- A Windows computer with XAMPP installed (Apache, PHP, MySQL/MariaDB)
- A web browser (for example Chrome or Edge)
- An internet connection for Bootstrap and Bootstrap Icons from a CDN

STEP 1 - COPY THE WEBSITE
1. Extract SSLMS_FINAL_WEBSITE.zip.
2. Find the extracted folder named "sslms".
3. Copy that folder into C:\xampp\htdocs\
4. Check that this file exists: C:\xampp\htdocs\sslms\index.php
   Avoid an extra nested folder such as htdocs\sslms\sslms\index.php.

STEP 2 - START XAMPP
1. Open the XAMPP Control Panel.
2. Click Start beside Apache.
3. Click Start beside MySQL.
4. Both services should show as running before you continue.

STEP 3 - INSTALL THE DATABASE (NEW INSTALLATION ONLY)
IMPORTANT: If you already have an SSLMS database with saved information,
back it up first. Do NOT import the fresh-install SQL over it.
1. Open http://localhost/phpmyadmin/ in your browser.
2. Choose the Import tab (from the phpMyAdmin home page).
3. Select C:\xampp\htdocs\sslms\sslms.sql
4. Click Import / Go.
5. The SQL script creates the database named "sslms_db" and seven tables:
   users, assignments, work_shifts, transactions, monthly_budgets,
   reminders and calendar_events.
6. In the left sidebar, expand sslms_db and check that the tables exist.

STEP 4 - OPEN AND USE SSLMS
1. Visit http://localhost/sslms/auth/register.php
2. Register a new test-student account.
3. Sign in at http://localhost/sslms/auth/login.php
4. The dashboard is at http://localhost/sslms/
5. Use the navigation menu to explore assignments, study planning,
   finances, budgets, shifts, reminders, calendar, profile and analytics.

DATABASE SETTINGS
The default connection settings are in sslms/config/db.php:
  Host: localhost
  Database: sslms_db
  Username: root
  Password: blank (default local XAMPP installation)
If your MySQL login differs, change config/db.php to match your setup.
The project also supports these configuration variables:
SSLMS_DB_HOST, SSLMS_DB_NAME, SSLMS_DB_USER, SSLMS_DB_PASSWORD.

WHERE TO FIND THE SOURCE CODE
  sslms/index.php                 Dashboard
  sslms/auth/                     Register, login and logout
  sslms/academics/                Assignments and Study Planner
  sslms/finance/                  Income, expenses, budgets and prediction
  sslms/work/                     Work shifts and balance advisor
  sslms/calendar/                 Calendar events and integrated calendar
  sslms/reminders/                Reminder management
  sslms/profile/                  Student profile
  sslms/analytics/                Analytics
  sslms/guide/                    Website guide
  sslms/config/db.php             MySQL connection
  sslms/includes/                 Shared PHP files
  sslms/assets/css/style.css      Stylesheet
  sslms/sslms.sql                 Database setup SQL

TROUBLESHOOTING
- "localhost refused to connect": Start Apache in XAMPP; check its port.
- "Page not found" / 404: Check folder path and URL, especially nested folders.
- "Database connection failed": Start MySQL, import the SQL schema for a
  fresh installation and check sslms/config/db.php.
- Import error "database already exists" or conflicting tables: Stop and
  check whether this is an existing populated database; do not delete data.
- Missing design/icons: Check internet access (the interface uses CDN files).
- Keep the website folder and database export backed up before any updates.

KNOWN LIMITATIONS
- Schedule-image recognition is not available: the processing component is
  not included in this website package.
- PHP syntax and selected source-level tests passed, but full end-to-end
  browser and MySQL testing on a new XAMPP installation is still required.
- This is intended for local demonstration, not public internet deployment
  without further security and operational review.
- The ZIP does not contain any existing student accounts or private records.

START HERE: http://localhost/sslms/auth/register.php
