<?php
/**
 * Add / Edit Book Form Page
 *
 * Enforces login, ownership (when editing), CSRF validation, category lookup
 * checks, and secure image uploads verified via finfo and getimagesize().
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Book;

Auth::requireLogin();

$userId = (int) Auth::id();
$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['book_id'])) {
    $postedId = filter_var($_POST['book_id'], FILTER_VALIDATE_INT);
    if ($postedId !== false && $postedId > 0) {
        $bookId = $postedId;
    }
}

$isEdit = ($bookId !== false && $bookId !== null && $bookId > 0);
$existingBook = null;

if ($isEdit) {
    $existingBook = Book::findByIdWithOwner((int) $bookId);
    if ($existingBook === null) {
        require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
        exit;
    }

    // Server-side ownership check (never rely on hidden UI buttons)
    if ((int) $existingBook['owner_id'] !== $userId) {
        http_response_code(403);
        setFlash('danger', 'You do not have permission to edit that book.');
        redirect('my-books.php');
    }

    if ($existingBook['status'] !== 'available') {
        setFlash('warning', 'Reserved or swapped books cannot be edited.');
        redirect('my-books.php');
    }
}

$categories = Book::getCategories();

$form = [
    'title'          => $existingBook['title'] ?? '',
    'author'         => $existingBook['author'] ?? '',
    'category_id'    => isset($existingBook['category_id']) ? (int) $existingBook['category_id'] : 0,
    'book_condition' => $existingBook['book_condition'] ?? 'good',
    'description'    => $existingBook['description'] ?? '',
    'image'          => $existingBook['image'] ?? null,
];

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::verifyFormPost()) {
        $errors[] = 'Your security token expired. Please submit the form again.';
    } else {
        $form['title'] = trim((string) ($_POST['title'] ?? ''));
        $form['author'] = trim((string) ($_POST['author'] ?? ''));
        $form['category_id'] = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $form['book_condition'] = trim((string) ($_POST['book_condition'] ?? ''));
        $form['description'] = trim((string) ($_POST['description'] ?? ''));
        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

        if (mb_strlen($form['title']) < 2 || mb_strlen($form['title']) > 200) {
            $errors[] = 'Book title must be between 2 and 200 characters.';
        }
        if (mb_strlen($form['author']) < 2 || mb_strlen($form['author']) > 150) {
            $errors[] = 'Author name must be between 2 and 150 characters.';
        }
        if ($form['category_id'] <= 0 || !Book::categoryExists($form['category_id'])) {
            $errors[] = 'Please choose a valid book category.';
        }
        if (!in_array($form['book_condition'], bookConditions(), true)) {
            $errors[] = 'Please choose a valid book condition.';
        }
        if (mb_strlen($form['description']) > 2000) {
            $errors[] = 'Description must be 2000 characters or fewer.';
        }

        // Handle optional book image upload
        $uploadedFilename = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Could not upload the book image. Please try again.';
            } elseif ((int) $file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Book image must be 2 MB or smaller.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $mimeMap = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                if (!is_string($mime) || !isset($mimeMap[$mime]) || @getimagesize($file['tmp_name']) === false) {
                    $errors[] = 'Book image must be a valid JPG, PNG, or WebP image.';
                } else {
                    if (!is_dir(UPLOAD_DIR)) {
                        mkdir(UPLOAD_DIR, 0755, true);
                    }
                    $uploadedFilename = bin2hex(random_bytes(16)) . '.' . $mimeMap[$mime];
                    $destination = UPLOAD_DIR . DIRECTORY_SEPARATOR . $uploadedFilename;
                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        $errors[] = 'Could not save the uploaded image. Please try again.';
                        $uploadedFilename = null;
                    }
                }
            }
        }

        if (empty($errors)) {
            $imageToSave = $form['image'];
            if ($uploadedFilename !== null) {
                $imageToSave = $uploadedFilename;
            } elseif ($removeImage) {
                $imageToSave = null;
            }

            $payload = [
                'category_id'    => $form['category_id'],
                'title'          => $form['title'],
                'author'         => $form['author'],
                'book_condition' => $form['book_condition'],
                'description'    => $form['description'],
                'image'          => $imageToSave,
            ];

            if ($isEdit) {
                $result = Book::updateByOwner((int) $bookId, $userId, $payload);
                if ($result['ok']) {
                    setFlash('success', $result['message']);
                    redirect('my-books.php');
                }
                Book::removeImageFile($uploadedFilename);
                $errors[] = $result['message'];
            } else {
                Book::create($userId, $payload);
                setFlash('success', 'Your book has been listed for swaps!');
                redirect('my-books.php');
            }
        } else {
            // Clean up newly uploaded file if form validation failed
            Book::removeImageFile($uploadedFilename);
        }
    }
}

$pageTitle = $isEdit ? 'Edit Book' : 'Add New Book';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 fw-bold text-primary mb-0"><?= $isEdit ? 'Edit Book Listing' : 'List a Book for Swap' ?></h1>
                <a href="<?= e(url('my-books.php')) ?>" class="btn btn-outline-secondary btn-sm">Back to My Books</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST"
                  action="<?= e(url($isEdit ? 'book-form.php?id=' . (int) $bookId : 'book-form.php')) ?>"
                  enctype="multipart/form-data"
                  class="js-validate-form"
                  novalidate>
                <?= Csrf::field() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="book_id" value="<?= e((string) $bookId) ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="title" class="form-label">Book Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               class="form-control"
                               required
                               minlength="2"
                               maxlength="200"
                               value="<?= e($form['title']) ?>">
                        <div class="invalid-feedback">Please enter the book title (2-200 characters).</div>
                    </div>

                    <div class="col-md-6">
                        <label for="author" class="form-label">Author</label>
                        <input type="text"
                               id="author"
                               name="author"
                               class="form-control"
                               required
                               minlength="2"
                               maxlength="150"
                               value="<?= e($form['author']) ?>">
                        <div class="invalid-feedback">Please enter the author name.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Category</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            <option value="">Choose...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e((string) $cat['id']) ?>"
                                    <?= $form['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a category.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="book_condition" class="form-label">Condition</label>
                        <select id="book_condition" name="book_condition" class="form-select" required>
                            <?php foreach (bookConditions() as $cond): ?>
                                <option value="<?= e($cond) ?>" <?= $form['book_condition'] === $cond ? 'selected' : '' ?>>
                                    <?= e(ucfirst($cond)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description &amp; Notes <span class="text-muted small">(optional)</span></label>
                        <textarea id="description"
                                  name="description"
                                  rows="3"
                                  maxlength="2000"
                                  class="form-control"
                                  placeholder="Edition details, highlighting notes, or preferred campus meetup spot..."><?= e($form['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="image" class="form-label">Cover Image <span class="text-muted small">(JPG, PNG, WebP, max 2 MB)</span></label>
                        <input type="file"
                               id="image"
                               name="image"
                               class="form-control"
                               accept="image/jpeg,image/png,image/webp">
                        <div class="invalid-feedback">Please upload a JPG, PNG, or WebP image under 2 MB.</div>

                        <?php if ($isEdit && !empty($form['image'])): ?>
                            <div class="mt-2 d-flex align-items-center gap-3">
                                <img src="<?= e(url('uploads/' . rawurlencode((string) $form['image']))) ?>"
                                     alt="Current book cover"
                                     class="rounded border"
                                     style="height: 70px; width: 55px; object-fit: cover;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="remove_image" name="remove_image">
                                    <label class="form-check-label small" for="remove_image">Remove current cover image</label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Publish Book' ?></button>
                    <a href="<?= e(url('my-books.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
