<?php
/**
 * API Endpoint: Send Swap Request (POST)
 *
 * Called by Fetch on book.php when a logged-in student offers one of their
 * own available books in exchange for another student's available book.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('POST', true);

$body = api_input();
$requestedBookId = isset($body['requested_book_id']) ? (int) $body['requested_book_id'] : 0;
$offeredBookId = isset($body['offered_book_id']) ? (int) $body['offered_book_id'] : 0;

if ($requestedBookId <= 0 || $offeredBookId <= 0) {
    json_out(['ok' => false, 'message' => 'Please choose a valid book to offer.'], 400);
}

[$ok, $message] = Swap::create((int) Auth::id(), $requestedBookId, $offeredBookId);
json_out(['ok' => $ok, 'message' => $message], $ok ? 201 : 400);
