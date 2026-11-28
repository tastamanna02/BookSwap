-- BookSwap Database Schema (MySQL / MariaDB - InnoDB, utf8mb4)
-- Safe to run multiple times thanks to IF NOT EXISTS and INSERT IGNORE.

CREATE DATABASE IF NOT EXISTS bookswap
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bookswap;

-- ---------------------------------------------------------------------------
-- 1. users: registered students and admin accounts, plus login lockout fields
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    failed_attempts TINYINT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. categories: lookup table for book genres/subjects
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. books: books listed by users for exchange
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(150) NOT NULL,
    book_condition ENUM('new', 'good', 'fair', 'worn') NOT NULL DEFAULT 'good',
    description TEXT NULL,
    image VARCHAR(255) NULL DEFAULT NULL,
    status ENUM('available', 'reserved') NOT NULL DEFAULT 'available',
    price DECIMAL(10,2) NULL DEFAULT NULL,
    price_type ENUM('fixed', 'bargaining') NOT NULL DEFAULT 'fixed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indexes speed up search and browse queries on index.php
    INDEX idx_books_status (status),
    INDEX idx_books_title (title),
    INDEX idx_books_author (author),

    -- Delete a user's books automatically if their account is deleted
    CONSTRAINT fk_books_owner
        FOREIGN KEY (owner_id) REFERENCES users(id)
        ON DELETE CASCADE,

    -- Keep the book if a category is ever deleted; just set category_id to NULL
    CONSTRAINT fk_books_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. swap_requests: tracks book-for-book proposals and dual confirmation
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS swap_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL,
    owner_id INT UNSIGNED NOT NULL,
    requested_book_id INT UNSIGNED NOT NULL,
    offered_book_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    requester_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    owner_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL DEFAULT NULL,

    -- Both participants must exist in users; cascade removes requests if a user is deleted
    CONSTRAINT fk_swap_requester
        FOREIGN KEY (requester_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_swap_owner
        FOREIGN KEY (owner_id) REFERENCES users(id)
        ON DELETE CASCADE,

    -- Both books in the swap must exist in books; cascade removes requests if a book is deleted
    CONSTRAINT fk_swap_requested_book
        FOREIGN KEY (requested_book_id) REFERENCES books(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_swap_offered_book
        FOREIGN KEY (offered_book_id) REFERENCES books(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. Seed the 8 default categories without duplicating rows on re-import
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO categories (name) VALUES
('Textbook'),
('Fiction'),
('Non-fiction'),
('Science & Tech'),
('Literature'),
('Self-help'),
('Children'),
('Other');
