<?php
/**
 * API Endpoint: Delete Book (POST)
 *
 * Protected by api_guard('POST', true). Reads `id` from JSON, verifies the
 * book exists (404), checks owner or admin permission (403), refuses deletion
 * if the book is in an accepted swap (422), deletes the row + image, and
 * returns JSON { ok, message }.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('POST', true);

$body = api_input();
$bookId = isset($body['id']) ? (int) $body['id'] : (int) ($body['book_id'] ?? 0);

if ($bookId <= 0) {
    json_out(['ok' => false, 'message' => 'Invalid book ID.'], 400);
}

$book = Book::find($bookId);
if ($book === null) {
    json_out(['ok' => false, 'message' => 'Book not found.'], 404);
}

// Server-side permission check: only the book owner or an admin may delete
if ((int) $book['owner_id'] !== (int) Auth::id() && !Auth::isAdmin()) {
    json_out(['ok' => false, 'message' => 'You do not have permission to delete this book.'], 403);
}

// Refuse deletion while the book is locked in an accepted swap
if (Swap::bookInActiveSwap($bookId)) {
    json_out([
        'ok'      => false,
        'message' => 'This book is currently part of an accepted swap and cannot be deleted.',
    ], 422);
}

Book::delete($bookId);
Book::deleteImage($book['image']);

json_out([
    'ok'      => true,
    'message' => 'Book deleted successfully.',
]);
