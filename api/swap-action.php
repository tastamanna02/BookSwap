<?php
/**
 * API Endpoint: Swap Lifecycle Action (POST)
 *
 * Delegates to Swap::act() for accept, decline, cancel, and confirm actions.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('POST', true);

$body = api_input();
$swapId = isset($body['swap_id']) ? (int) $body['swap_id'] : (int) ($body['id'] ?? 0);
$action = isset($body['action']) && is_string($body['action']) ? trim($body['action']) : '';

[$ok, $message] = Swap::act($swapId, (int) Auth::id(), $action);
json_out(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
