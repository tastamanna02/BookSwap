<?php
/**
 * API Endpoint: Delete Own Book (POST)
 *
 * Authenticated endpoint for my-books.php. Verifies CSRF header via api_guard()
 * and enforces server-side ownership and 'available' status before deleting.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('POST', true);

$body = api_input();
$bookId = isset($body['book_id']) ? (int) $body['book_id'] : 0;

if ($bookId <= 0) {
    json_out(['ok' => false, 'message' => 'Invalid book selected.'], 400);
}

$result = Book::deleteByOwner($bookId, (int) Auth::id());
json_out($result, $result['ok'] ? 200 : 400);
