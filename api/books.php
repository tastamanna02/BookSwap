<?php
/**
 * API Endpoint: Search Available Books (GET)
 *
 * Public endpoint called by Fetch on index.php to filter available books
 * by keyword, category, and physical condition using prepared statements.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Models\Book;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : '';
$condition = isset($_GET['condition']) && is_string($_GET['condition']) ? trim($_GET['condition']) : '';

if (mb_strlen($query) > 100) {
    jsonResponse(['ok' => false, 'message' => 'Search text is too long.'], 400);
}

$books = Book::searchAvailable($query, $category, $condition);

jsonResponse([
    'ok'    => true,
    'books' => $books,
]);
