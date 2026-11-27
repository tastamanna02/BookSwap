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

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Swap;

Auth::requireApiLogin();
Csrf::requireValidApiPost();

$body = getJsonBody();
$requestedBookId = isset($body['requested_book_id']) ? (int) $body['requested_book_id'] : 0;
$offeredBookId = isset($body['offered_book_id']) ? (int) $body['offered_book_id'] : 0;

if ($requestedBookId <= 0 || $offeredBookId <= 0) {
    jsonResponse(['ok' => false, 'message' => 'Please choose a valid book to offer.'], 400);
}

$result = Swap::createRequest((int) Auth::id(), $requestedBookId, $offeredBookId);
$statusCode = $result['ok'] ? 201 : 400;

jsonResponse($result, $statusCode);
