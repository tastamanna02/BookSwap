<?php
/**
 * API Endpoint: Admin Delete Any Book (POST)
 *
 * Strictly enforces admin role and X-CSRF-Token on the server before
 * deleting any book and freeing any partner book from active swaps.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Csrf;
use App\Core\Auth;
use App\Models\Book;

Auth::requireApiAdmin();
Csrf::requireValidApiPost();

$body = getJsonBody();
$bookId = isset($body['book_id']) ? (int) $body['book_id'] : 0;

if ($bookId <= 0) {
    jsonResponse(['ok' => false, 'message' => 'Invalid book ID.'], 400);
}

$result = Book::deleteByAdmin($bookId);
$statusCode = $result['ok'] ? 200 : 404;

jsonResponse($result, $statusCode);
