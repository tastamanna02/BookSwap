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

use App\Core\Auth;
use App\Models\Swap;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

Auth::requireApiLogin();

$grouped = Swap::getUserSwapsGrouped((int) Auth::id());

jsonResponse([
    'ok'       => true,
    'incoming' => $grouped['incoming'],
    'outgoing' => $grouped['outgoing'],
    'history'  => $grouped['history'],
]);
