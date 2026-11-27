<?php
/**
 * API Endpoint: Search Available Books (GET)
 *
 * Public endpoint called by Fetch on index.php to filter available books
 * by keyword, category_id, and physical condition using prepared statements.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('GET', false);

$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$condition = isset($_GET['condition']) && is_string($_GET['condition']) ? trim($_GET['condition']) : '';

if (mb_strlen($query) > 100) {
    json_out(['ok' => false, 'message' => 'Search text is too long.'], 400);
}

$books = Book::searchAvailable($query, $categoryId, $condition);

json_out([
    'ok'    => true,
    'books' => $books,
]);
