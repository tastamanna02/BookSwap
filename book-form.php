<?php
/**
 * Add / Edit Book Form Page
 *
 * Supports creating a new listing or editing an existing one (?id=).
 * Enforces login, ownership (show_404() if missing or not owned), CSRF check,
 * category validation, and finfo-verified cover uploads via Book::handleUpload().
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

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
    $existingBook = Book::find((int) $bookId);
    // Only allow editing if the book exists AND belongs to the logged-in user
    if ($existingBook === null || (int) $existingBook['owner_id'] !== $userId) {
        show_404();
    }

    if ($existingBook['status'] !== 'available') {
        flash('warning', 'Reserved or swapped books cannot be edited.');
        redirect('my-books.php');
    }
}

$categories = Book::categories();

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
    if (!Csrf::check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your security token expired. Please submit the form again.';
    } else {
        $form['title'] = trim((string) ($_POST['title'] ?? ''));
        $form['author'] = trim((string) ($_POST['author'] ?? ''));
        $form['category_id'] = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $form['book_condition'] = trim((string) ($_POST['book_condition'] ?? ''));
        $form['description'] = trim((string) ($_POST['description'] ?? ''));
        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

        if ($form['title'] === '' || mb_strlen($form['title']) > 200) {
            $errors[] = 'Book title is required and must be 200 characters or fewer.';
        }
        if ($form['author'] === '' || mb_strlen($form['author']) > 150) {
            $errors[] = 'Author name is required and must be 150 characters or fewer.';
        }
        if ($form['category_id'] <= 0 || !Book::categoryExists($form['category_id'])) {
            $errors[] = 'Please choose a valid book category.';
        }
        if (!array_key_exists($form['book_condition'], Book::CONDITIONS)) {
            $errors[] = 'Please choose a valid book condition.';
        }
        if (mb_strlen($form['description']) > 2000) {
            $errors[] = 'Description must be 2000 characters or fewer.';
        }

        // Validate and move optional uploaded cover image
        [$uploadedFilename, $uploadError] = Book::handleUpload($_FILES['image'] ?? []);
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }

        if (empty($errors)) {
            $oldImage = $form['image'];
            $imageToSave = $oldImage;

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
                Book::update((int) $bookId, $payload);
                // Delete old cover image only when replaced or removed
                if ($imageToSave !== $oldImage) {
                    Book::deleteImage($oldImage);
                }
                flash('success', 'Book updated successfully.');
            } else {
                Book::create($userId, $payload);
                flash('success', 'Your book has been listed for swaps!');
            }

            redirect('my-books.php');
        } else {
            // Remove newly uploaded image if form validation failed
            Book::deleteImage($uploadedFilename);
        }
    }
}

$title = $isEdit ? 'Edit Book' : 'Add New Book';
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
                  class="needs-check"
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
                               maxlength="200"
                               value="<?= e($form['title']) ?>">
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-6">
                        <label for="author" class="form-label">Author</label>
                        <input type="text"
                               id="author"
                               name="author"
                               class="form-control"
                               required
                               maxlength="150"
                               value="<?= e($form['author']) ?>">
                        <div class="invalid-feedback"></div>
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
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-6">
                        <label for="book_condition" class="form-label">Condition</label>
                        <select id="book_condition" name="book_condition" class="form-select" required>
                            <?php foreach (Book::CONDITIONS as $condValue => $condLabel): ?>
                                <option value="<?= e($condValue) ?>" <?= $form['book_condition'] === $condValue ? 'selected' : '' ?>>
                                    <?= e($condLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description &amp; Notes <span class="text-muted small">(optional)</span></label>
                        <textarea id="description"
                                  name="description"
                                  rows="3"
                                  maxlength="2000"
                                  class="form-control"
                                  placeholder="Edition details, highlighting notes, or preferred campus meetup spot..."><?= e($form['description'] ?? '') ?></textarea>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-12">
                        <label for="image" class="form-label">Cover Image <span class="text-muted small">(JPG, PNG, WebP, max 2 MB)</span></label>
                        <input type="file"
                               id="image"
                               name="image"
                               class="form-control"
                               data-max-mb="2"
                               accept="image/jpeg,image/png,image/webp">
                        <div class="invalid-feedback"></div>

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
