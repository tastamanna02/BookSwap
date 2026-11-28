<?php
/**
 * User Profile Page
 *
 * Allows authenticated users to view their activity summary, update their
 * display name, and change their password safely with CSRF and password_verify().
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

Auth::requireLogin();

$authUser = Auth::user();
$userId = (int) $authUser['id'];
// Fetch full user row (ID, name, email, role, password_hash) for verification
$user = User::findByEmail((string) $authUser['email']) ?? $authUser;

$profileErrors = [];
$passwordErrors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::check($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Security token expired. Please try again.');
        redirect('profile.php');
    }

    if (isset($_POST['update_name'])) {
        $name = trim((string) ($_POST['name'] ?? ''));

        if (empty($name) || mb_strlen($name) > 100) {
            $profileErrors[] = 'Name is required and must be 100 characters or fewer.';
        }

        if (empty($profileErrors)) {
            User::updateName($userId, $name);
            // Keep session copy synced if present; Auth::user() also reloads from DB on redirect
            $_SESSION['user_name'] = $name;
            flash('success', 'Name updated successfully.');
            redirect('profile.php');
        }

        $user['name'] = $name;
    } elseif (isset($_POST['update_password'])) {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || !password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            $passwordErrors[] = 'Your current password is incorrect.';
        }

        if (strlen($newPassword) < 8) {
            $passwordErrors[] = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordErrors[] = 'New password and confirmation do not match.';
        }

        if (empty($passwordErrors)) {
            User::updatePassword($userId, $newPassword);
            flash('success', 'Password updated successfully.');
            redirect('profile.php');
        }
    }
}

$bookStats = Book::getStatsForOwner($userId);
$completedSwaps = Swap::completedCount($userId);

$title = 'My Profile';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm p-4 mb-4">
            <h1 class="h4 fw-bold text-primary mb-1"><?= e($user['name']) ?></h1>
            <p class="text-muted small mb-2"><?= e($user['email']) ?></p>
            <p class="small text-muted mb-0">
                Role: <span class="badge bg-secondary text-uppercase"><?= e($user['role']) ?></span>
            </p>
        </div>

        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Your Activity Summary</h2>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span>Books Listed</span>
                    <strong><?= e((string) $bookStats['total']) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span>Currently Available</span>
                    <strong><?= e((string) $bookStats['available']) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span>Completed Swaps</span>
                    <strong><?= e((string) $completedSwaps) ?></strong>
                </li>
            </ul>
            <div class="d-grid gap-2 mt-3">
                <a href="<?= e(url('my-books.php')) ?>" class="btn btn-outline-primary btn-sm">Manage My Books</a>
                <a href="<?= e(url('swaps.php')) ?>" class="btn btn-outline-secondary btn-sm">View Swap History</a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-4 mb-4">
            <h2 class="h5 fw-bold mb-3">Edit Account Name</h2>

            <?php if (!empty($profileErrors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($profileErrors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('profile.php')) ?>" class="needs-check" novalidate>
                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="form-control"
                           required
                           maxlength="100"
                           value="<?= e($user['name']) ?>">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                </div>

                <button type="submit" name="update_name" value="1" class="btn btn-primary">Save Name</button>
            </form>
        </div>

        <div class="card border-0 shadow-sm p-4">
            <h2 class="h5 fw-bold mb-3">Change Password</h2>

            <?php if (!empty($passwordErrors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($passwordErrors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('profile.php')) ?>" class="needs-check" novalidate>
                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" data-match="#new_password" required minlength="8">
                    <div class="invalid-feedback"></div>
                </div>

                <button type="submit" name="update_password" value="1" class="btn btn-outline-primary">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
