-- BookSwap Database Schema for MySQL / MariaDB (XAMPP)
-- Import this file via phpMyAdmin or the MySQL CLI before running the app.

CREATE DATABASE IF NOT EXISTS bookswap
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bookswap;

-- ---------------------------------------------------------------------------
-- 1. Users table: stores registered students and admin accounts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    university VARCHAR(150) NOT NULL DEFAULT '',
    bio TEXT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Books table: stores books listed by students for exchange
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(150) NOT NULL,
    isbn VARCHAR(30) NULL,
    category VARCHAR(80) NOT NULL,
    book_condition ENUM('New', 'Like New', 'Good', 'Fair') NOT NULL DEFAULT 'Good',
    description TEXT NULL,
    cover_image VARCHAR(255) NULL,
    status ENUM('available', 'reserved', 'swapped') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_books_status_created (status, created_at),
    INDEX idx_books_user (user_id),
    CONSTRAINT fk_books_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Swaps table: tracks swap lifecycle and dual-confirmation history
-- Status flow: pending -> accepted -> completed, OR declined / cancelled
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS swaps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL,
    owner_id INT UNSIGNED NOT NULL,
    requested_book_id INT UNSIGNED NULL,
    offered_book_id INT UNSIGNED NULL,
    -- Snapshot titles preserve swap history even if an admin deletes a book later
    requested_book_title VARCHAR(200) NOT NULL,
    offered_book_title VARCHAR(200) NOT NULL,
    message VARCHAR(500) NULL,
    status ENUM('pending', 'accepted', 'completed', 'declined', 'cancelled') NOT NULL DEFAULT 'pending',
    requester_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    owner_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    requester_confirmed_at DATETIME NULL,
    owner_confirmed_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_swaps_requester (requester_id, status),
    INDEX idx_swaps_owner (owner_id, status),
    INDEX idx_swaps_books (requested_book_id, offered_book_id),
    CONSTRAINT fk_swaps_requester
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_swaps_owner
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_swaps_req_book
        FOREIGN KEY (requested_book_id) REFERENCES books(id) ON DELETE SET NULL,
    CONSTRAINT fk_swaps_off_book
        FOREIGN KEY (offered_book_id) REFERENCES books(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Optional sample seed accounts & books for quick viva testing
-- Demo passwords (hashed with PHP password_hash PASSWORD_DEFAULT):
--   Admin:   admin@bookswap.test   / Admin@123
--   Student: alice@bookswap.test   / Student@123
--   Student: bob@bookswap.test     / Student@123
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO users (id, name, email, password_hash, university, bio, role) VALUES
(1, 'Campus Admin', 'admin@bookswap.test', '$2y$10$L7oKgArTEC9nDwQZ75I2teMxSEL1cMFTNMbSV5DmDFBpDesAL3lpa', 'University Library Office', 'BookSwap platform moderator.', 'admin'),
(2, 'Alice Rahman', 'alice@bookswap.test', '$2y$10$zQEvl40/EIEbqQb3ZJHDAuPGKdLQ5fZ/fWlH/juY7deW7IlLrLPuq', 'State University - CS Dept', 'Computer Science senior looking for algorithms and design books.', 'user'),
(3, 'Bob Karim', 'bob@bookswap.test', '$2y$10$zQEvl40/EIEbqQb3ZJHDAuPGKdLQ5fZ/fWlH/juY7deW7IlLrLPuq', 'State University - Economics', 'Trading economics textbooks and sci-fi paperbacks.', 'user');

INSERT IGNORE INTO books (id, user_id, title, author, isbn, category, book_condition, description, status) VALUES
(1, 2, 'Clean Code', 'Robert C. Martin', '978-0132350884', 'Computer Science', 'Like New', 'Barely used hardcover copy, no highlights inside.', 'available'),
(2, 2, 'Introduction to Algorithms', 'Cormen, Leiserson, Rivest, Stein', '978-0262046305', 'Computer Science', 'Good', 'Minor edge wear on cover; all pages crisp and clean.', 'available'),
(3, 3, 'Principles of Economics', 'N. Gregory Mankiw', '978-0357038314', 'Economics & Business', 'Good', 'Used for ECON 101 last semester. Happy to trade for tech or fiction.', 'available'),
(4, 3, 'Dune', 'Frank Herbert', '978-0441172719', 'Fiction & Literature', 'New', 'Brand new paperback duplicate gift.', 'available');
