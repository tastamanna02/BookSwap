<?php
/**
 * User Profile Page
 *
 * Allows authenticated users to view their swap stats, update profile
 * information, and change their password safely using CSRF and password_verify.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Book;
use App\Models\Swap;
use App\Models\User;

Auth::requireLogin();

$user = Auth::user();
$userId = (int) $user['id'];
$profileErrors = [];
$passwordErrors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $formType = (string) ($_POST['form_type'] ?? '');

    if (!Csrf::verifyFormPost()) {
        setFlash('danger', 'Security token expired. Please try again.');
        redirect('profile.php');
    }

    if ($formType === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $university = trim((string) ($_POST['university'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $profileErrors[] = 'Name must be between 2 and 100 characters.';
        }
        if (mb_strlen($university) > 150) {
            $profileErrors[] = 'University / department must be 150 characters or fewer.';
        }
        if (mb_strlen($bio) > 500) {
            $profileErrors[] = 'Bio must be 500 characters or fewer.';
        }

        if (empty($profileErrors)) {
            User::updateProfile($userId, $name, $university, $bio);
            setFlash('success', 'Profile updated successfully.');
            redirect('profile.php');
        }

        // Keep unsaved form input visible when validation fails
        $user['name'] = $name;
        $user['university'] = $university;
        $user['bio'] = $bio;
    } elseif ($formType === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '') {
            $passwordErrors[] = 'Please enter your current password.';
        }
        if (strlen($newPassword) < 8) {
            $passwordErrors[] = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordErrors[] = 'New password and confirmation do not match.';
        }

        if (empty($passwordErrors)) {
            $result = User::updatePassword($userId, $currentPassword, $newPassword);
            if ($result['ok']) {
                setFlash('success', $result['message']);
                redirect('profile.php');
            }
            $passwordErrors[] = $result['message'];
        }
    }
}

$bookStats = Book::getStatsForUser($userId);
$completedSwaps = Swap::countCompletedForUser($userId);

$pageTitle = 'My Profile';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm p-4 mb-4">
            <h1 class="h4 fw-bold text-primary mb-1"><?= e($user['name']) ?></h1>
            <p class="text-muted small mb-2"><?= e($user['email']) ?></p>
            <?php if (!empty($user['university'])): ?>
                <p class="small mb-2"><strong>Campus:</strong> <?= e($user['university']) ?></p>
            <?php endif; ?>
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
            <h2 class="h5 fw-bold mb-3">Edit Profile</h2>

            <?php if (!empty($profileErrors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($profileErrors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('profile.php')) ?>" class="js-validate-form" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="form_type" value="profile">

                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="form-control"
                           required
                           minlength="2"
                           maxlength="100"
                           value="<?= e($user['name']) ?>">
                    <div class="invalid-feedback">Please enter your name (2-100 characters).</div>
                </div>

                <div class="mb-3">
                    <label for="university" class="form-label">University / Department</label>
                    <input type="text"
                           id="university"
                           name="university"
                           class="form-control"
                           maxlength="150"
                           value="<?= e($user['university']) ?>">
                </div>

                <div class="mb-3">
                    <label for="bio" class="form-label">Short Bio / Preferred Genres</label>
                    <textarea id="bio"
                              name="bio"
                              rows="3"
                              maxlength="500"
                              class="form-control"
                              placeholder="Tell fellow students where on campus you prefer to meet or what books you enjoy..."><?= e($user['bio'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save Profile</button>
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

            <form method="POST" action="<?= e(url('profile.php')) ?>" class="js-validate-form" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="form_type" value="password">

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                    <div class="invalid-feedback">Please enter your current password.</div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                    <div class="invalid-feedback">New password must be at least 8 characters.</div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                    <div class="invalid-feedback">Passwords must match.</div>
                </div>

                <button type="submit" class="btn btn-outline-primary">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
