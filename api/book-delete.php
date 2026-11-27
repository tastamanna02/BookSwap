<?php
/**
 * API Endpoint: Delete Own Book (POST)
 *
 * Authenticated endpoint for my-books.php. Verifies CSRF token and enforces
 * server-side ownership and 'available' status before deleting the book.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Book;

Auth::requireApiLogin();
Csrf::requireValidApiPost();

$body = getJsonBody();
$bookId = isset($body['book_id']) ? (int) $body['book_id'] : 0;

if ($bookId <= 0) {
    jsonResponse(['ok' => false, 'message' => 'Invalid book selected.'], 400);
}

$result = Book::deleteByOwner($bookId, (int) Auth::id());
$statusCode = $result['ok'] ? 200 : 400;

jsonResponse($result, $statusCode);
