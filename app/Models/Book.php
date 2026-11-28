<?php
/**
 * Book Model
 *
 * Manages book listings, categories lookup, search/filtering, secure image
 * uploads (finfo MIME inspection + 24-hex filenames), and transactional deletes.
 */

declare(strict_types=1);

class Book
{
    /**
     * Allowed condition values mapped to human-friendly labels.
     */
    public const CONDITIONS = [
        'new'  => 'Like new',
        'good' => 'Good',
        'fair' => 'Fair',
        'worn' => 'Worn',
    ];

    private const MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 MB

    /**
     * Escape SQL LIKE wildcard characters so user searches match literal text.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Return all book categories ordered by ID.
     */
    public static function categories(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    /**
     * Verify that a given category_id exists in the categories table.
     */
    public static function categoryExists(int $categoryId): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$categoryId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Find a single book joined with its owner name and category name.
     */
    public static function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT b.id, b.owner_id, b.category_id, b.title, b.author,
                    b.book_condition, b.description, b.image, b.status, b.created_at,
                    u.name AS owner_name,
                    COALESCE(c.name, 'Uncategorized') AS category_name
             FROM books b
             INNER JOIN users u ON b.owner_id = u.id
             LEFT JOIN categories c ON b.category_id = c.id
             WHERE b.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $book = $stmt->fetch();

        return $book !== false ? $book : null;
    }

    /**
     * List books owned by a given user; optionally filter to 'available' only.
     */
    public static function byOwner(int $ownerId, bool $onlyAvailable = false): array
    {
        $pdo = Database::pdo();

        $sql = "SELECT b.id, b.owner_id, b.category_id, b.title, b.author,
                       b.book_condition, b.description, b.image, b.status, b.created_at,
                       COALESCE(c.name, 'Uncategorized') AS category_name,
                       EXISTS(
                           SELECT 1 FROM swap_requests sr
                           WHERE sr.status = 'completed'
                             AND (sr.requested_book_id = b.id OR sr.offered_book_id = b.id)
                       ) AS is_swapped
                FROM books b
                LEFT JOIN categories c ON b.category_id = c.id
                WHERE b.owner_id = ?";

        $params = [$ownerId];

        if ($onlyAvailable) {
            $sql .= ' AND b.status = ?';
            $params[] = 'available';
        }

        $sql .= " ORDER BY FIELD(b.status, 'available', 'reserved'), b.created_at DESC, b.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Create a new book listing owned by the given user.
     */
    public static function create(int $ownerId, array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO books
             (owner_id, category_id, title, author, book_condition, description, image, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $ownerId,
            $data['category_id'],
            $data['title'],
            $data['author'],
            $data['book_condition'],
            $data['description'] !== '' ? $data['description'] : null,
            $data['image'] ?? null,
            'available',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update editable book columns for the given book ID.
     */
    public static function update(int $id, array $data): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE books
             SET category_id = ?, title = ?, author = ?,
                 book_condition = ?, description = ?, image = ?
             WHERE id = ?'
        );

        return $stmt->execute([
            $data['category_id'],
            $data['title'],
            $data['author'],
            $data['book_condition'],
            $data['description'] !== '' ? $data['description'] : null,
            $data['image'] ?? null,
            $id,
        ]);
    }

    /**
     * Delete a book inside a transaction, freeing any partner book reserved
     * in an accepted swap, and remove its uploaded image file.
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT id, image FROM books WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $book = $stmt->fetch();

            if ($book === false) {
                $pdo->rollBack();
                return false;
            }

            // Un-reserve partner books if this book was part of an accepted swap
            $swapStmt = $pdo->prepare(
                "SELECT requested_book_id, offered_book_id
                 FROM swap_requests
                 WHERE status = 'accepted'
                   AND (requested_book_id = :b1 OR offered_book_id = :b2)
                 FOR UPDATE"
            );
            $swapStmt->execute([':b1' => $id, ':b2' => $id]);

            $partnerIds = [];
            foreach ($swapStmt->fetchAll() as $swap) {
                $reqId = (int) $swap['requested_book_id'];
                $offId = (int) $swap['offered_book_id'];
                if ($reqId !== $id) {
                    $partnerIds[] = $reqId;
                }
                if ($offId !== $id) {
                    $partnerIds[] = $offId;
                }
            }

            if (!empty($partnerIds)) {
                $partnerIds = array_values(array_unique($partnerIds));
                $placeholders = implode(',', array_fill(0, count($partnerIds), '?'));
                $releaseStmt = $pdo->prepare(
                    "UPDATE books
                     SET status = 'available'
                     WHERE status = 'reserved' AND id IN ($placeholders)"
                );
                $releaseStmt->execute($partnerIds);
            }

            $del = $pdo->prepare('DELETE FROM books WHERE id = ?');
            $del->execute([$id]);

            $pdo->commit();
            self::deleteImage($book['image']);

            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Fetch the most recently listed books (optionally filtered to available only).
     */
    public static function recent(int $limit = 12, bool $onlyAvailable = true): array
    {
        $pdo = Database::pdo();
        $where = $onlyAvailable ? "WHERE b.status = 'available'" : '';
        $stmt = $pdo->prepare(
            "SELECT b.id, b.owner_id, b.category_id, b.title, b.author,
                    b.book_condition, b.description, b.image, b.status, b.created_at,
                    u.name AS owner_name,
                    COALESCE(c.name, 'Uncategorized') AS category_name
             FROM books b
             INNER JOIN users u ON b.owner_id = u.id
             LEFT JOIN categories c ON b.category_id = c.id
             $where
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Return aggregate book counts (total, available, and reserved).
     */
    public static function counts(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved
             FROM books"
        );
        $row = $stmt->fetch();

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'available' => (int) ($row['available'] ?? 0),
            'reserved'  => (int) ($row['reserved'] ?? 0),
        ];
    }

    /**
     * Validate and save an uploaded cover image using finfo MIME inspection.
     * Returns [filename, errorMessage] (returns [null, null] when no file was sent).
     */
    public static function handleUpload(array $file): array
    {
        if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [null, 'Could not upload the cover image. Please try again.'];
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES) {
            return [null, 'Cover image must be 2 MB or smaller.'];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return [null, 'Invalid uploaded file.'];
        }

        // Detect real MIME type from file bytes via finfo (never trust extension or browser type)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!is_string($mime) || !isset($mimeMap[$mime])) {
            return [null, 'Cover image must be a real JPG, PNG, or WebP file.'];
        }

        if (@getimagesize($tmpPath) === false) {
            return [null, 'Cover image appears corrupted or invalid.'];
        }

        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }

        // 12 random bytes -> 24 hex characters + safe extension from MIME map
        $filename = bin2hex(random_bytes(12)) . '.' . $mimeMap[$mime];
        $destination = UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $destination)) {
            return [null, 'Could not save the uploaded image. Please try again.'];
        }

        return [$filename, null];
    }

    /**
     * Safely remove an uploaded image file from uploads/.
     */
    public static function deleteImage(?string $name): void
    {
        if ($name === null || $name === '') {
            return;
        }

        // basename() prevents directory traversal like ../../config/database.php
        $safeName = basename($name);
        $fullPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Paginated search for available books by title/author and optional category.
     * Returns ['items', 'total', 'page', 'pages'].
     */
    public static function search(string $q = '', int $categoryId = 0, int $page = 1, int $perPage = 8): array
    {
        $pdo = Database::pdo();

        $conditions = ["b.status = 'available'"];
        $trimmed = trim($q);
        $like = '';

        if ($trimmed !== '') {
            $like = '%' . self::escapeLike($trimmed) . '%';
            // Two distinct named placeholders because PDO::ATTR_EMULATE_PREPARES is false
            $conditions[] = '(b.title LIKE :q1 OR b.author LIKE :q2)';
        }

        if ($categoryId > 0) {
            $conditions[] = 'b.category_id = :category_id';
        }

        $whereSql = implode(' AND ', $conditions);

        // 1. Count total matching available books first
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM books b WHERE $whereSql");
        if ($trimmed !== '') {
            $countStmt->bindValue(':q1', $like, PDO::PARAM_STR);
            $countStmt->bindValue(':q2', $like, PDO::PARAM_STR);
        }
        if ($categoryId > 0) {
            $countStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        // 2. Clamp page between 1 and total pages
        $perPage = max(1, min(50, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        // 3. Fetch paginated items with owner name and category name joined
        $selectSql = "SELECT b.id, b.owner_id, b.category_id, b.title, b.author,
                             b.book_condition, b.description, b.image, b.status, b.created_at,
                             u.name AS owner_name,
                             COALESCE(c.name, 'Uncategorized') AS category_name
                      FROM books b
                      INNER JOIN users u ON b.owner_id = u.id
                      LEFT JOIN categories c ON b.category_id = c.id
                      WHERE $whereSql
                      ORDER BY b.created_at DESC, b.id DESC
                      LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($selectSql);
        if ($trimmed !== '') {
            $stmt->bindValue(':q1', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q2', $like, PDO::PARAM_STR);
        }
        if ($categoryId > 0) {
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    /**
     * Delete an owner's available book safely (used by JSON delete endpoints).
     */
    public static function deleteByOwner(int $bookId, int $ownerId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT id, owner_id, status, image
                 FROM books
                 WHERE id = ?
                 FOR UPDATE'
            );
            $stmt->execute([$bookId]);
            $book = $stmt->fetch();

            if ($book === false) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Book not found.'];
            }

            if ((int) $book['owner_id'] !== $ownerId) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'You do not have permission to delete this book.'];
            }

            if ($book['status'] !== 'available') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Reserved or swapped books cannot be deleted.'];
            }

            $del = $pdo->prepare('DELETE FROM books WHERE id = ? AND owner_id = ?');
            $del->execute([$bookId, $ownerId]);

            $pdo->commit();
            self::deleteImage($book['image']);

            return ['ok' => true, 'message' => 'Book deleted successfully.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Admin deletion wrapper returning a JSON-ready status array.
     */
    public static function deleteByAdmin(int $bookId): array
    {
        $deleted = self::delete($bookId);
        return $deleted
            ? ['ok' => true, 'message' => 'Book removed by admin.']
            : ['ok' => false, 'message' => 'Book not found.'];
    }

    /**
     * List all books across all users for the admin dashboard.
     */
    public static function listAllForAdmin(string $query = ''): array
    {
        $pdo = Database::pdo();

        $sql = "SELECT b.id, b.title, b.author, b.book_condition, b.status, b.created_at,
                       COALESCE(c.name, 'Uncategorized') AS category_name,
                       u.name AS owner_name, u.email AS owner_email
                FROM books b
                INNER JOIN users u ON b.owner_id = u.id
                LEFT JOIN categories c ON b.category_id = c.id";
        $params = [];

        $trimmed = trim($query);
        if ($trimmed !== '') {
            $like = '%' . self::escapeLike($trimmed) . '%';
            $sql .= ' WHERE (b.title LIKE :q1 OR b.author LIKE :q2 OR u.name LIKE :q3 OR u.email LIKE :q4)';
            $params = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
        }

        $sql .= ' ORDER BY b.created_at DESC, b.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Count total, available, and reserved books listed by one user.
     */
    public static function getStatsForOwner(int $ownerId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved
             FROM books
             WHERE owner_id = ?"
        );
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch();

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'available' => (int) ($row['available'] ?? 0),
            'reserved'  => (int) ($row['reserved'] ?? 0),
        ];
    }
}
