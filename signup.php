<?php
/**
 * Student Sign Up Page
 *
 * Validates registration input server-side and client-side (.needs-check),
 * verifies CSRF, creates the user with password_hash(), logs them in,
 * flashes a welcome message, and redirects to my-books.php.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

Auth::requireGuest();

$errors = [];
$old = [
    'name'  => '',
    'email' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your security token expired. Please try submitting the form again.';
    } else {
        $old['name'] = trim((string) ($_POST['name'] ?? ''));
        $old['email'] = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
            $errors[] = 'Full name is required and must be 100 characters or fewer.';
        }

        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (User::findByEmail($old['email']) !== null) {
            $errors[] = 'An account with that email already exists.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $newId = User::create($old['name'], $old['email'], $password);
            $newUser = User::find($newId);
            if ($newUser !== null) {
                Auth::login($newUser);
                flash('success', 'Welcome to BookSwap, ' . $newUser['name'] . '! Start by adding your first book.');
                redirect('my-books.php');
            }
            $errors[] = 'Something went wrong, try again.';
        }
    }
}

$title = 'Sign Up';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm p-4">
            <h1 class="h4 fw-bold text-primary mb-1">Create Your Student Account</h1>
            <p class="text-muted small mb-4">Join BookSwap to list your books and exchange with peers.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('signup.php')) ?>" class="needs-check" novalidate>
                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="form-control"
                           required
                           maxlength="100"
                           value="<?= e($old['name']) ?>">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control"
                           required
                           maxlength="150"
                           value="<?= e($old['email']) ?>">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control"
                           required
                           minlength="8">
                    <div class="form-text">Minimum 8 characters.</div>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password"
                           id="confirm_password"
                           name="confirm_password"
                           class="form-control"
                           required
                           minlength="8">
                    <div class="invalid-feedback"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Sign Up</button>
            </form>

            <p class="text-center small text-muted mt-3 mb-0">
                Already have an account? <a href="<?= e(url('login.php')) ?>">Log in here</a>.
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
