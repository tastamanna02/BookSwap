<?php
/**
 * API Endpoint: Unified Swap Endpoint (POST)
 *
 * Protected by api_guard('POST', true). Routes 'request' actions to Swap::create()
 * and all lifecycle actions (accept, decline, cancel, confirm) to Swap::act(),
 * returning JSON { ok, message } with HTTP 200 or 422.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('POST', true);

$body = api_input();
$action = isset($body['action']) && is_string($body['action']) ? trim($body['action']) : '';
$userId = (int) Auth::id();

if ($action === 'request') {
    $requestedBookId = isset($body['requested_book_id']) ? (int) $body['requested_book_id'] : 0;
    $offeredBookId = isset($body['offered_book_id']) ? (int) $body['offered_book_id'] : 0;
    [$ok, $message] = Swap::create($userId, $requestedBookId, $offeredBookId);
} else {
    $swapId = isset($body['swap_id']) ? (int) $body['swap_id'] : (int) ($body['id'] ?? 0);
    [$ok, $message] = Swap::act($swapId, $userId, $action);
}

json_out([
    'ok'      => $ok,
    'message' => $message,
], $ok ? 200 : 422);
