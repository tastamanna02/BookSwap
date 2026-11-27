<?php
/**
 * API Endpoint: Swap Lifecycle Action (POST)
 *
 * Handles accept, decline, cancel, and confirm actions via Fetch POST.
 * Enforces login, CSRF header, and server-side participant permissions.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('POST', true);

$body = api_input();
$swapId = isset($body['swap_id']) ? (int) $body['swap_id'] : 0;
$action = isset($body['action']) && is_string($body['action']) ? trim($body['action']) : '';

if ($swapId <= 0 || !in_array($action, ['accept', 'decline', 'cancel', 'confirm'], true)) {
    json_out(['ok' => false, 'message' => 'Invalid swap request or action.'], 400);
}

$result = Swap::handleAction($swapId, (int) Auth::id(), $action);
json_out($result, $result['ok'] ? 200 : 400);
