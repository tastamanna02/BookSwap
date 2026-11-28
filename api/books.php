<?php
/**
 * API Endpoint: Search Available Books (GET, public)
 *
 * Reads q (trimmed to 100 chars), category_id (or category), price_max,
 * swap_only (or swap-only), and page, and returns JSON ['ok' => true] + Book::search(...).
 */

declare(strict_types=1);

define('BOOKSWAP_API', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

api_guard('GET', false);

$q = isset($_GET['q']) && is_string($_GET['q'])
    ? mb_substr(trim($_GET['q']), 0, 100)
    : '';
$categoryId = isset($_GET['category_id'])
    ? (int) $_GET['category_id']
    : (int) ($_GET['category'] ?? 0);
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

$priceMax = null;
if (isset($_GET['price_max']) && is_string($_GET['price_max'])) {
    $rawPriceMax = trim($_GET['price_max']);
    if ($rawPriceMax !== '' && is_numeric($rawPriceMax)) {
        $parsedMax = (float) $rawPriceMax;
        if (is_finite($parsedMax) && $parsedMax >= 0) {
            $priceMax = $parsedMax;
        }
    }
}

$rawSwapOnly = $_GET['swap_only'] ?? ($_GET['swap-only'] ?? '');
$swapOnly = is_string($rawSwapOnly)
    && in_array(strtolower(trim($rawSwapOnly)), ['1', 'true', 'yes', 'on'], true);

json_out(['ok' => true] + Book::search($q, max(0, $categoryId), $page, 8, $priceMax, $swapOnly));
