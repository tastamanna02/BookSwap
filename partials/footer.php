<?php
/**
 * Shared Footer Partial
 *
 * Closes the main container, renders the page footer, loads Bootstrap 5 JS
 * via CDN, and includes the shared app.js plus any page-specific scripts.
 */

declare(strict_types=1);
?>
</main>

<footer class="bg-white border-top py-3 mt-auto">
    <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-center text-muted small gap-2">
        <span>BookSwap &mdash; Student-to-Student Book Exchange</span>
        <span>Free &bull; No Money Involved &bull; PHP 8 + MySQL</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $scriptPath): ?>
        <script src="<?= e(url($scriptPath)) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
