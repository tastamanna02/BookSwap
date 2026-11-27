# BookSwap

A student-to-student book exchange web application built with PHP 8 OOP (MVC-style, no framework), MySQL (PDO), HTML5, CSS3, Bootstrap 5 (CDN), and vanilla JavaScript (Fetch API). Designed to run on XAMPP.

## Quick Setup on XAMPP

1. **Place the project in XAMPP**
   Copy or symlink this `BookSwap` folder into `C:\xampp\htdocs\BookSwap` (or run `php -S localhost:8000` inside this folder for quick local testing once MySQL is running).
2. **Start Apache & MySQL**
   Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. **Import the Database**
   Open `http://localhost/phpmyadmin`, go to **Import**, and import `sql/schema.sql`.
   This creates the `bookswap` database, the 4 tables (`users`, `categories`, `books`, `swap_requests`), and the 8 default categories.
4. **Verify DB Config**
   Check `config/database.php` (defaults to `127.0.0.1`, database `bookswap`, user `root`, empty password).
5. **Create Accounts & Test**
   Open `http://localhost/BookSwap/index.php` and register student accounts via `signup.php`.
   To create an admin account for testing `admin.php`, register a user first and promote them in phpMyAdmin:
   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
   ```

## Swap Lifecycle

`pending` -> `accepted` -> `completed` (or `declined` / `cancelled`)

- While `pending`, books remain `available` so multiple offers can be received.
- When the book owner clicks **Accept**, both books become `reserved` inside a database transaction and competing pending requests on either book are declined.
- A swap becomes `completed` only when **both** students click **Confirm Completed**.
