<?php
/**
 * Login Page
 *
 * Verifies CSRF token and authenticates users using password_verify().
 * Regenerates the session ID via Auth::login() to prevent session fixation.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\User;

Auth::requireGuest();

$error = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::verifyFormPost()) {
        $error = 'Your security token expired. Please try logging in again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Please enter a valid email and password.';
        } else {
            $user = User::verifyCredentials($email, $password);
            if ($user === null) {
                // Keep login error generic so attackers cannot enumerate valid emails
                $error = 'Invalid email or password.';
            } else {
                Auth::login($user);
                setFlash('success', 'Welcome back, ' . $user['name'] . '!');
                redirect($user['role'] === 'admin' ? 'admin.php' : 'index.php');
            }
        }
    }
}

$pageTitle = 'Log In';
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

            <form method="POST" action="<?= e(url('login.php')) ?>" class="js-validate-form" novalidate>
                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control"
                           required
                           value="<?= e($email) ?>">
                    <div class="invalid-feedback">Please enter your email address.</div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control"
                           required>
                    <div class="invalid-feedback">Please enter your password.</div>
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
