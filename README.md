# BookSwap

A student-to-student book exchange web application built with PHP 8 OOP (MVC-style, no framework), MySQL (PDO), HTML5, CSS3, Bootstrap 5 (CDN), and vanilla JavaScript (Fetch API). Designed to run on XAMPP.

## Quick Setup on XAMPP

1. **Place the project in XAMPP**
   Copy or symlink this `BookSwap` folder into `C:\xampp\htdocs\BookSwap` (or run `php -S localhost:8000` inside this folder for quick local testing once MySQL is running).
2. **Start Apache & MySQL**
   Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. **Import the Database**
   Open `http://localhost/phpmyadmin`, go to **Import**, and import `sql/schema.sql`.
   This creates the `bookswap` database, tables (`users`, `books`, `swaps`), and optional demo accounts/books.
4. **Verify DB Config**
   Check `config/database.php` (defaults to `127.0.0.1`, database `bookswap`, user `root`, empty password).
5. **Open the App**
   Visit `http://localhost/BookSwap/index.php`.

## Demo Accounts (from `sql/schema.sql`)

- **Admin:** `admin@bookswap.test` / `Admin@123`
- **Student 1:** `alice@bookswap.test` / `Student@123`
- **Student 2:** `bob@bookswap.test` / `Student@123`

## Swap Lifecycle

`pending` -> `accepted` -> `completed` (or `declined` / `cancelled`)

- While `pending`, books remain available so multiple offers can be received.
- When the book owner clicks **Accept**, both books become `reserved` inside a database transaction and competing pending requests on either book are declined.
- A swap becomes `completed` (and both books become `swapped`) only when **both** students click **Confirm Completed**.
