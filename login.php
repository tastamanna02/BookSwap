<?php
/**
 * Login Page
 *
 * Verifies CSRF, checks lockout via User::isLocked(), authenticates with
 * password_verify(), tracks failed attempts via User::recordFailure(), and
 * resets failures + regenerates session ID on success.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

Auth::requireGuest();

$error = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::check($_POST['csrf_token'] ?? null)) {
        $error = 'Your security token expired. Please try logging in again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Wrong email or password';
        } else {
            $user = User::findByEmail($email);

            if ($user !== null && User::isLocked($user)) {
                $error = 'Too many failed attempts. Your account is temporarily locked. Please try again later.';
            } elseif ($user === null || !password_verify($password, $user['password_hash'])) {
                if ($user !== null) {
                    User::recordFailure($user);
                }
                // Generic error avoids revealing whether the email or password was wrong
                $error = 'Wrong email or password';
            } else {
                // Rehash transparently if PHP's default hashing algorithm/cost changes
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    User::updatePassword((int) $user['id'], $password);
                }

                User::resetFailures((int) $user['id']);
                Auth::login($user);
                flash('success', 'Welcome back, ' . $user['name'] . '!');
                redirect($user['role'] === 'admin' ? 'admin.php' : 'index.php');
            }
        }
    }
}

$title = 'Log In';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm p-4">
            <h1 class="h4 fw-bold text-primary mb-1">Log In</h1>
            <p class="text-muted small mb-4">Sign in to manage your books and swap requests.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('login.php')) ?>" class="needs-check" novalidate>
                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control"
                           required
                           value="<?= e($email) ?>">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control"
                           required>
                    <div class="invalid-feedback"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Log In</button>
            </form>

            <p class="text-center small text-muted mt-3 mb-0">
                New to BookSwap? <a href="<?= e(url('signup.php')) ?>">Create an account</a>.
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
