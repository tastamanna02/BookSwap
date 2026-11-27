<?php
/**
 * Book Model
 *
 * Manages book listings, search/filtering, owner edits, and transactional
 * deletions (by owner or admin) while keeping swap state consistent.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

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
     * Safely delete an uploaded cover image file from the uploads/ directory.
     */
    public static function removeCoverFile(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        // basename() prevents directory traversal attacks like ../../config/database.php
        $safeName = basename($filename);
        $fullPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Search and filter currently available books with owner metadata.
     */
    public static function searchAvailable(string $query = '', string $category = '', string $condition = ''): array
    {
        $pdo = Database::getConnection();

        $sql = 'SELECT b.id, b.user_id, b.title, b.author, b.isbn, b.category,
                       b.book_condition, b.description, b.cover_image, b.status, b.created_at,
                       u.name AS owner_name, u.university AS owner_university
                FROM books b
                INNER JOIN users u ON b.user_id = u.id
                WHERE b.status = :status';

        $params = [':status' => 'available'];

        $trimmedQuery = trim($query);
        if ($trimmedQuery !== '') {
            $like = '%' . self::escapeLike($trimmedQuery) . '%';
            // Distinct named parameters are required when PDO::ATTR_EMULATE_PREPARES is false
            $sql .= ' AND (b.title LIKE :q1 OR b.author LIKE :q2 OR b.isbn LIKE :q3)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        if ($category !== '' && in_array($category, bookCategories(), true)) {
            $sql .= ' AND b.category = :category';
            $params[':category'] = $category;
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
     * Fetch a single book with its owner details, or null if not found.
     */
    public static function findByIdWithOwner(int $bookId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT b.id, b.user_id, b.title, b.author, b.isbn, b.category,
                    b.book_condition, b.description, b.cover_image, b.status,
                    b.created_at, b.updated_at,
                    u.name AS owner_name, u.university AS owner_university, u.bio AS owner_bio
             FROM books b
             INNER JOIN users u ON b.user_id = u.id
             WHERE b.id = ?
             LIMIT 1'
        );
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        return $book !== false ? $book : null;
    }

    /**
     * List all books owned by a user (active/reserved first, then available, then swapped).
     */
    public static function findByUser(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, user_id, title, author, isbn, category, book_condition,
                    description, cover_image, status, created_at
             FROM books
             WHERE user_id = ?
             ORDER BY FIELD(status, 'reserved', 'available', 'swapped'), created_at DESC, id DESC"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * List only 'available' books owned by a user (used when picking a book to offer).
     */
    public static function findAvailableByUser(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, title, author, book_condition
             FROM books
             WHERE user_id = ? AND status = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$userId, 'available']);

        return $stmt->fetchAll();
    }

    /**
     * Create a new book listing owned by the given user.
     */
    public static function create(int $userId, array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO books
             (user_id, title, author, isbn, category, book_condition, description, cover_image, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['title'],
            $data['author'],
            $data['isbn'] !== '' ? $data['isbn'] : null,
            $data['category'],
            $data['book_condition'],
            $data['description'] !== '' ? $data['description'] : null,
            $data['cover_image'] ?? null,
            'available',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update a book listing after verifying ownership and that it is still available.
     */
    public static function updateByOwner(int $bookId, int $userId, array $data): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT id, user_id, status, cover_image
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

            if ((int) $existing['user_id'] !== $userId) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'You can only edit your own books.'];
            }

            if ($existing['status'] !== 'available') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Books in an active or completed swap cannot be edited.'];
            }

            $newCover = array_key_exists('cover_image', $data) ? $data['cover_image'] : $existing['cover_image'];

            $update = $pdo->prepare(
                'UPDATE books
                 SET title = ?, author = ?, isbn = ?, category = ?,
                     book_condition = ?, description = ?, cover_image = ?
                 WHERE id = ? AND user_id = ?'
            );
            $update->execute([
                $data['title'],
                $data['author'],
                $data['isbn'] !== '' ? $data['isbn'] : null,
                $data['category'],
                $data['book_condition'],
                $data['description'] !== '' ? $data['description'] : null,
                $newCover,
                $bookId,
                $userId,
            ]);

            $pdo->commit();

            // Delete old cover image only after the database update succeeds
            if ($newCover !== $existing['cover_image']) {
                self::removeCoverFile($existing['cover_image']);
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
     * Delete an owner's available book inside a transaction, cancelling any pending requests.
     */
    public static function deleteByOwner(int $bookId, int $userId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT id, user_id, status, cover_image
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

            if ((int) $book['user_id'] !== $userId) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'You do not have permission to delete this book.'];
            }

            if ($book['status'] !== 'available') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Reserved or swapped books cannot be deleted.'];
            }

            // Cancel any pending swap requests involving this book before removing it
            $cancelPending = $pdo->prepare(
                "UPDATE swaps
                 SET status = 'cancelled'
                 WHERE status = 'pending'
                   AND (requested_book_id = :b1 OR offered_book_id = :b2)"
            );
            $cancelPending->execute([':b1' => $bookId, ':b2' => $bookId]);

            $del = $pdo->prepare('DELETE FROM books WHERE id = ? AND user_id = ?');
            $del->execute([$bookId, $userId]);

            $pdo->commit();
            self::removeCoverFile($book['cover_image']);

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
     * Cancels active swaps and frees any reserved partner book back to 'available'.
     */
    public static function deleteByAdmin(int $bookId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT id, cover_image FROM books WHERE id = ? FOR UPDATE');
            $stmt->execute([$bookId]);
            $book = $stmt->fetch();

            if ($book === false) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Book not found.'];
            }

            // Lock any open (pending/accepted) swaps touching this book
            $swapStmt = $pdo->prepare(
                "SELECT id, requested_book_id, offered_book_id, status
                 FROM swaps
                 WHERE status IN ('pending', 'accepted')
                   AND (requested_book_id = :b1 OR offered_book_id = :b2)
                 FOR UPDATE"
            );
            $swapStmt->execute([':b1' => $bookId, ':b2' => $bookId]);
            $openSwaps = $swapStmt->fetchAll();

            // Release any partner book that was reserved in an accepted swap
            $partnerIds = [];
            foreach ($openSwaps as $swap) {
                if ($swap['status'] === 'accepted') {
                    $reqId = $swap['requested_book_id'] !== null ? (int) $swap['requested_book_id'] : null;
                    $offId = $swap['offered_book_id'] !== null ? (int) $swap['offered_book_id'] : null;

                    if ($reqId !== null && $reqId !== $bookId) {
                        $partnerIds[] = $reqId;
                    }
                    if ($offId !== null && $offId !== $bookId) {
                        $partnerIds[] = $offId;
                    }
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

            // Mark open swaps as cancelled; historical swaps remain intact via ON DELETE SET NULL
            $cancelSwaps = $pdo->prepare(
                "UPDATE swaps
                 SET status = 'cancelled'
                 WHERE status IN ('pending', 'accepted')
                   AND (requested_book_id = :b1 OR offered_book_id = :b2)"
            );
            $cancelSwaps->execute([':b1' => $bookId, ':b2' => $bookId]);

            $del = $pdo->prepare('DELETE FROM books WHERE id = ?');
            $del->execute([$bookId]);

            $pdo->commit();
            self::removeCoverFile($book['cover_image']);

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
        $pdo = Database::getConnection();

        $sql = 'SELECT b.id, b.title, b.author, b.category, b.book_condition, b.status,
                       b.created_at, u.name AS owner_name, u.email AS owner_email
                FROM books b
                INNER JOIN users u ON b.user_id = u.id';
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
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            "SELECT
                COUNT(*) AS total_books,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_books,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved_books,
                SUM(CASE WHEN status = 'swapped' THEN 1 ELSE 0 END) AS swapped_books
             FROM books"
        );
        $row = $stmt->fetch();

        return [
            'total_books'     => (int) ($row['total_books'] ?? 0),
            'available_books' => (int) ($row['available_books'] ?? 0),
            'reserved_books'  => (int) ($row['reserved_books'] ?? 0),
            'swapped_books'   => (int) ($row['swapped_books'] ?? 0),
        ];
    }

    /**
     * Count total and available books listed by one user.
     */
    public static function getStatsForUser(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN status = 'swapped' THEN 1 ELSE 0 END) AS swapped
             FROM books
             WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'available' => (int) ($row['available'] ?? 0),
            'swapped'   => (int) ($row['swapped'] ?? 0),
        ];
    }
}
