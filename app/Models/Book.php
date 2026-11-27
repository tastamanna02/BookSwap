<?php
/**
 * Book Model
 *
 * Manages book listings, categories lookup, search/filtering, owner edits,
 * and transactional deletions (by owner or admin) aligned with schema.sql.
 */

declare(strict_types=1);

class Book
{
    /**
     * Escape SQL LIKE wildcard characters so user searches match literal text.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Safely delete an uploaded book image file from the uploads/ directory.
     */
    public static function removeImageFile(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        // basename() prevents directory traversal attacks
        $safeName = basename($filename);
        $fullPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Fetch all categories from the categories table in insertion order.
     */
    public static function getCategories(): array
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
     * Search and filter currently available books with owner and category names.
     */
    public static function searchAvailable(string $query = '', int $categoryId = 0, string $condition = ''): array
    {
        $pdo = Database::pdo();

        $sql = 'SELECT b.id, b.owner_id, b.category_id, b.title, b.author,
                       b.book_condition, b.description, b.image, b.status, b.created_at,
                       u.name AS owner_name,
                       COALESCE(c.name, "Uncategorized") AS category_name
                FROM books b
                INNER JOIN users u ON b.owner_id = u.id
                LEFT JOIN categories c ON b.category_id = c.id
                WHERE b.status = :status';

        $params = [':status' => 'available'];

        $trimmedQuery = trim($query);
        if ($trimmedQuery !== '') {
            $like = '%' . self::escapeLike($trimmedQuery) . '%';
            // Distinct named parameters are required when PDO::ATTR_EMULATE_PREPARES is false
            $sql .= ' AND (b.title LIKE :q1 OR b.author LIKE :q2)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }

        if ($categoryId > 0) {
            $sql .= ' AND b.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        if ($condition !== '' && in_array($condition, bookConditions(), true)) {
            $sql .= ' AND b.book_condition = :condition';
            $params[':condition'] = $condition;
        }

        $sql .= ' ORDER BY b.created_at DESC, b.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Fetch a single book with its owner name and category name, or null if not found.
     */
    public static function findByIdWithOwner(int $bookId): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT b.id, b.owner_id, b.category_id, b.title, b.author,
                    b.book_condition, b.description, b.image, b.status, b.created_at,
                    u.name AS owner_name,
                    COALESCE(c.name, "Uncategorized") AS category_name
             FROM books b
             INNER JOIN users u ON b.owner_id = u.id
             LEFT JOIN categories c ON b.category_id = c.id
             WHERE b.id = ?
             LIMIT 1'
        );
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        return $book !== false ? $book : null;
    }

    /**
     * List all books owned by a user, along with whether a reserved book has completed a swap.
     */
    public static function findByOwner(int $ownerId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT b.id, b.owner_id, b.category_id, b.title, b.author,
                    b.book_condition, b.description, b.image, b.status, b.created_at,
                    COALESCE(c.name, 'Uncategorized') AS category_name,
                    EXISTS(
                        SELECT 1 FROM swap_requests sr
                        WHERE sr.status = 'completed'
                          AND (sr.requested_book_id = b.id OR sr.offered_book_id = b.id)
                    ) AS is_swapped
             FROM books b
             LEFT JOIN categories c ON b.category_id = c.id
             WHERE b.owner_id = ?
             ORDER BY FIELD(b.status, 'available', 'reserved'), b.created_at DESC, b.id DESC"
        );
        $stmt->execute([$ownerId]);

        return $stmt->fetchAll();
    }

    /**
     * List only 'available' books owned by a user (used when choosing a book to offer).
     */
    public static function findAvailableByOwner(int $ownerId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, title, author, book_condition
             FROM books
             WHERE owner_id = ? AND status = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$ownerId, 'available']);

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
     * Update a book listing after verifying ownership and 'available' status.
     */
    public static function updateByOwner(int $bookId, int $ownerId, array $data): array
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
            $existing = $stmt->fetch();

            if ($existing === false) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Book not found.'];
            }

            if ((int) $existing['owner_id'] !== $ownerId) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'You can only edit your own books.'];
            }

            if ($existing['status'] !== 'available') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Reserved or swapped books cannot be edited.'];
            }

            $newImage = array_key_exists('image', $data) ? $data['image'] : $existing['image'];

            $update = $pdo->prepare(
                'UPDATE books
                 SET category_id = ?, title = ?, author = ?,
                     book_condition = ?, description = ?, image = ?
                 WHERE id = ? AND owner_id = ?'
            );
            $update->execute([
                $data['category_id'],
                $data['title'],
                $data['author'],
                $data['book_condition'],
                $data['description'] !== '' ? $data['description'] : null,
                $newImage,
                $bookId,
                $ownerId,
            ]);

            $pdo->commit();

            // Delete old image only after the database update succeeds
            if ($newImage !== $existing['image']) {
                self::removeImageFile($existing['image']);
            }

            return ['ok' => true, 'message' => 'Book updated successfully.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete an owner's available book inside a transaction.
     * Cascading FKs clean up any pending swap_requests referencing this book.
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
            self::removeImageFile($book['image']);

            return ['ok' => true, 'message' => 'Book deleted successfully.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Admin deletion of any book inside a transaction.
     * Frees any partner book that was reserved in an accepted swap before deleting.
     */
    public static function deleteByAdmin(int $bookId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT id, image FROM books WHERE id = ? FOR UPDATE');
            $stmt->execute([$bookId]);
            $book = $stmt->fetch();

            if ($book === false) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Book not found.'];
            }

            // Find any accepted swaps involving this book so the partner's book is un-reserved
            $swapStmt = $pdo->prepare(
                "SELECT requested_book_id, offered_book_id
                 FROM swap_requests
                 WHERE status = 'accepted'
                   AND (requested_book_id = :b1 OR offered_book_id = :b2)
                 FOR UPDATE"
            );
            $swapStmt->execute([':b1' => $bookId, ':b2' => $bookId]);

            $partnerIds = [];
            foreach ($swapStmt->fetchAll() as $swap) {
                $reqId = (int) $swap['requested_book_id'];
                $offId = (int) $swap['offered_book_id'];
                if ($reqId !== $bookId) {
                    $partnerIds[] = $reqId;
                }
                if ($offId !== $bookId) {
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

            // ON DELETE CASCADE on swap_requests cleans up swap rows referencing this book
            $del = $pdo->prepare('DELETE FROM books WHERE id = ?');
            $del->execute([$bookId]);

            $pdo->commit();
            self::removeImageFile($book['image']);

            return ['ok' => true, 'message' => 'Book removed by admin.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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
     * Return aggregate book counts for admin metrics.
     */
    public static function getStatsForAdmin(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT
                COUNT(*) AS total_books,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_books,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved_books
             FROM books"
        );
        $row = $stmt->fetch();

        return [
            'total_books'     => (int) ($row['total_books'] ?? 0),
            'available_books' => (int) ($row['available_books'] ?? 0),
            'reserved_books'  => (int) ($row['reserved_books'] ?? 0),
        ];
    }

    /**
     * Count total and available books listed by one user.
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
