<?php
/**
 * API Endpoint: List User Swaps (GET)
 *
 * Returns the authenticated user's incoming, outgoing, and historical swaps
 * so swaps.js can refresh the UI smoothly after a swap action.
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('GET', true);

$userId = (int) Auth::id();
$grouped = Swap::getUserSwapsGrouped($userId);

json_out([
    'ok'               => true,
    'pending_incoming' => Swap::pendingIncoming($userId),
    'incoming'         => $grouped['incoming'],
    'outgoing'         => $grouped['outgoing'],
    'history'          => $grouped['history'],
]);
