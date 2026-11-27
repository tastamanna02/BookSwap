<?php
/**
 * API Endpoint: Admin Delete Any Book (POST)
 *
 * Strictly enforces admin role and X-CSRF-Token header on the server before
 * deleting any book and freeing partner books from active swaps.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('POST', true);

if (!Auth::isAdmin()) {
    json_out(['ok' => false, 'message' => 'You do not have permission to perform this action.'], 403);
}

$body = api_input();
$bookId = isset($body['book_id']) ? (int) $body['book_id'] : 0;

if ($bookId <= 0) {
    json_out(['ok' => false, 'message' => 'Invalid book ID.'], 400);
}

$result = Book::deleteByAdmin($bookId);
json_out($result, $result['ok'] ? 200 : 404);
