<?php
/**
 * Shared Footer Partial
 *
 * Closes <main>, renders small footer text, loads Bootstrap 5.3 JS bundle
 * from jsDelivr, loads assets/js/app.js, and loads an optional $pageScript.
 */

declare(strict_types=1);
?>
</main>

<footer class="bg-white border-top py-3 mt-auto">
    <div class="container text-center text-muted">
        <small>BookSwap &mdash; Student-to-Student Book Exchange &bull; No Money Involved</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php if (isset($pageScript) && is_string($pageScript) && $pageScript !== ''): ?>
    <script src="<?= e(url($pageScript)) ?>"></script>
<?php endif; ?>
</body>
</html>
