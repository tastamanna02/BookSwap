<?php
/**
 * 404 Not Found Page
 *
 * Sends a clean HTTP 404 status and renders a friendly page guiding the
 * visitor back to the main BookSwap catalog without exposing errors.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (!headers_sent()) {
    http_response_code(404);
}

$pageTitle = 'Page Not Found';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-5 text-center">
            <div class="display-5 fw-bold text-primary mb-2">404</div>
            <h1 class="h4 mb-2">Page or Book Not Found</h1>
            <p class="text-muted mb-4">
                The book or page you are looking for may have been removed or the link may be incorrect.
            </p>
            <div>
                <a href="<?= e(url('index.php')) ?>" class="btn btn-primary">Browse Available Books</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
