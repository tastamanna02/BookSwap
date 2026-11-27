<?php
/**
 * API Endpoint: Delete Book (POST)
 *
 * Called via Fetch POST with X-CSRF-Token by [data-delete-book] buttons.
 * Enforces server-side permissions: admins can delete any book (freeing any
 * reserved partner book), while regular users can only delete their own available books.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('POST', true);

$body = api_input();
$bookId = isset($body['book_id']) ? (int) $body['book_id'] : (int) ($body['id'] ?? 0);

if ($bookId <= 0) {
    json_out(['ok' => false, 'message' => 'Invalid book selected.'], 400);
}

$result = Auth::isAdmin()
    ? Book::deleteByAdmin($bookId)
    : Book::deleteByOwner($bookId, (int) Auth::id());

json_out($result, $result['ok'] ? 200 : 400);
