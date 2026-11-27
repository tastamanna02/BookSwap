<?php
/**
 * API Endpoint: Search Available Books (GET, public)
 *
 * Reads q (trimmed to 100 chars), category_id (or category), and page,
 * and returns JSON ['ok' => true] + Book::search(...).
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

json_out(['ok' => true] + Book::search($q, max(0, $categoryId), $page));
