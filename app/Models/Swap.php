<?php
/**
 * Swap Model
 *
 * Manages the swap_requests lifecycle inside atomic transactions:
 * pending -> accepted -> completed (dual confirm swaps book ownership),
 * or declined / cancelled.
 */

declare(strict_types=1);

final class Swap
{
    /**
     * Create a new 'pending' swap request inside a transaction with FOR UPDATE row locks.
     * Returns [bool ok, string message].
     */
    public static function create(int $userId, int $requestedBookId, int $offeredBookId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            if ($requestedBookId <= 0 || $offeredBookId <= 0) {
                throw new RuntimeException('Please select valid books for the swap.');
            }

            if ($requestedBookId === $offeredBookId) {
                throw new RuntimeException('You cannot swap a book with itself.');
            }

            // Lock both books in ascending ID order to avoid deadlocks
            $firstId = min($requestedBookId, $offeredBookId);
            $secondId = max($requestedBookId, $offeredBookId);
            $firstBook = self::lockBook($pdo, $firstId);
            $secondBook = self::lockBook($pdo, $secondId);

            $books = [];
            if ($firstBook !== null) {
                $books[(int) $firstBook['id']] = $firstBook;
            }
            if ($secondBook !== null) {
                $books[(int) $secondBook['id']] = $secondBook;
            }

            if (!isset($books[$requestedBookId], $books[$offeredBookId])) {
                throw new RuntimeException('One or both selected books could not be found.');
            }

            $requestedBook = $books[$requestedBookId];
            $offeredBook = $books[$offeredBookId];

            if ((int) $requestedBook['owner_id'] === $userId) {
                throw new RuntimeException('You cannot request a swap for your own book.');
            }

            if ((int) $offeredBook['owner_id'] !== $userId) {
                throw new RuntimeException('You can only offer a book that you own.');
            }

            if ($requestedBook['status'] !== 'available' || $offeredBook['status'] !== 'available') {
                throw new RuntimeException('Both books must be available to start a swap.');
            }

            // Ensure no pending request already exists for the same two books
            $dupStmt = $pdo->prepare(
                "SELECT id FROM swap_requests
                 WHERE requested_book_id = ? AND offered_book_id = ? AND status = 'pending'
                 LIMIT 1"
            );
            $dupStmt->execute([$requestedBookId, $offeredBookId]);
            if ($dupStmt->fetchColumn() !== false) {
                throw new RuntimeException('A pending swap request for these two books already exists.');
            }

            $insert = $pdo->prepare(
                "INSERT INTO swap_requests
                 (requester_id, owner_id, requested_book_id, offered_book_id, status)
                 VALUES (?, ?, ?, ?, 'pending')"
            );
            $insert->execute([
                $userId,
                (int) $requestedBook['owner_id'],
                $requestedBookId,
                $offeredBookId,
            ]);

            $pdo->commit();
            return [true, 'Swap request sent! You can track it on the My Swaps page.'];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [false, $e->getMessage()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Perform a swap state transition (decline, accept, cancel, or confirm)
     * inside a transaction with FOR UPDATE row locks. Returns [bool ok, string message].
     */
    public static function act(int $swapId, int $userId, string $action): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            if ($swapId <= 0) {
                throw new RuntimeException('Invalid swap request.');
            }

            $stmt = $pdo->prepare('SELECT * FROM swap_requests WHERE id = ? FOR UPDATE');
            $stmt->execute([$swapId]);
            $swap = $stmt->fetch();

            if ($swap === false) {
                throw new RuntimeException('Swap request not found.');
            }

            $ownerId = (int) $swap['owner_id'];
            $requesterId = (int) $swap['requester_id'];
            $isOwner = ($ownerId === $userId);
            $isRequester = ($requesterId === $userId);

            if (!$isOwner && !$isRequester) {
                throw new RuntimeException('You do not have permission to modify this swap.');
            }

            $reqBookId = (int) $swap['requested_book_id'];
            $offBookId = (int) $swap['offered_book_id'];
            $message = '';

            if ($action === 'decline') {
                if (!$isOwner) {
                    throw new RuntimeException('Only the book owner can decline this request.');
                }
                if ($swap['status'] !== 'pending') {
                    throw new RuntimeException('Only pending requests can be declined.');
                }

                self::setStatus($pdo, $swapId, 'declined');
                $message = 'Swap request declined.';
            } elseif ($action === 'accept') {
                if (!$isOwner) {
                    throw new RuntimeException('Only the book owner can accept this request.');
                }
                if ($swap['status'] !== 'pending') {
                    throw new RuntimeException('Only pending requests can be accepted.');
                }

                // Lock both books in ascending ID order before checking availability
                $firstBook = self::lockBook($pdo, min($reqBookId, $offBookId));
                $secondBook = self::lockBook($pdo, max($reqBookId, $offBookId));

                if ($firstBook === null || $secondBook === null) {
                    throw new RuntimeException('One of the books in this swap no longer exists.');
                }
                if ($firstBook['status'] !== 'available' || $secondBook['status'] !== 'available') {
                    throw new RuntimeException('Both books must still be available to accept this swap.');
                }

                self::setBooks($pdo, [$reqBookId, $offBookId], 'reserved');
                self::setStatus($pdo, $swapId, 'accepted');

                // Decline every other pending swap using either reserved book
                $declineOthers = $pdo->prepare(
                    "UPDATE swap_requests
                     SET status = 'declined'
                     WHERE id <> :swap_id
                       AND status = 'pending'
                       AND (
                           requested_book_id IN (:r1, :r2)
                           OR offered_book_id IN (:o1, :o2)
                       )"
                );
                $declineOthers->execute([
                    ':swap_id' => $swapId,
                    ':r1'      => $reqBookId,
                    ':r2'      => $offBookId,
                    ':o1'      => $reqBookId,
                    ':o2'      => $offBookId,
                ]);

                $message = 'Swap accepted! Both users must confirm once the books are exchanged.';
            } elseif ($action === 'cancel') {
                if ($swap['status'] === 'pending') {
                    if (!$isRequester) {
                        throw new RuntimeException('Only the requester can cancel a pending request.');
                    }
                    self::setStatus($pdo, $swapId, 'cancelled');
                    $message = 'Swap request cancelled.';
                } elseif ($swap['status'] === 'accepted') {
                    // Either participant may cancel an accepted swap and release both books
                    self::lockBook($pdo, min($reqBookId, $offBookId));
                    self::lockBook($pdo, max($reqBookId, $offBookId));
                    self::setBooks($pdo, [$reqBookId, $offBookId], 'available');
                    self::setStatus($pdo, $swapId, 'cancelled');
                    $message = 'Accepted swap cancelled. Both books are available again.';
                } else {
                    throw new RuntimeException('This swap can no longer be cancelled.');
                }
            } elseif ($action === 'confirm') {
                if ($swap['status'] !== 'accepted') {
                    throw new RuntimeException('Only accepted swaps can be confirmed.');
                }

                $reqConfirmed = (int) $swap['requester_confirmed'];
                $ownerConfirmed = (int) $swap['owner_confirmed'];

                if ($isRequester) {
                    if ($reqConfirmed === 1) {
                        throw new RuntimeException('You have already confirmed this swap.');
                    }
                    $reqConfirmed = 1;
                    $pdo->prepare('UPDATE swap_requests SET requester_confirmed = 1 WHERE id = ?')
                        ->execute([$swapId]);
                } else {
                    if ($ownerConfirmed === 1) {
                        throw new RuntimeException('You have already confirmed this swap.');
                    }
                    $ownerConfirmed = 1;
                    $pdo->prepare('UPDATE swap_requests SET owner_confirmed = 1 WHERE id = ?')
                        ->execute([$swapId]);
                }

                if ($reqConfirmed === 1 && $ownerConfirmed === 1) {
                    // Both parties confirmed: transfer ownership of both books and release them
                    self::lockBook($pdo, min($reqBookId, $offBookId));
                    self::lockBook($pdo, max($reqBookId, $offBookId));

                    $transferStmt = $pdo->prepare('UPDATE books SET owner_id = ? WHERE id = ?');
                    $transferStmt->execute([$requesterId, $reqBookId]);
                    $transferStmt->execute([$ownerId, $offBookId]);

                    self::setBooks($pdo, [$reqBookId, $offBookId], 'available');

                    $pdo->prepare(
                        "UPDATE swap_requests SET status = 'completed', completed_at = NOW() WHERE id = ?"
                    )->execute([$swapId]);

                    $message = 'Swap completed! Book ownership has been updated.';
                } else {
                    $message = 'Waiting for the other person to confirm';
                }
            } else {
                throw new RuntimeException('Invalid swap action.');
            }

            $pdo->commit();
            return [true, $message];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [false, $e->getMessage()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Lock a single book row FOR UPDATE inside an open transaction.
     */
    private static function lockBook(PDO $pdo, int $bookId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, owner_id, title, status
             FROM books
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$bookId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Update a swap request's status column inside an open transaction.
     */
    private static function setStatus(PDO $pdo, int $swapId, string $status): void
    {
        $stmt = $pdo->prepare('UPDATE swap_requests SET status = ? WHERE id = ?');
        $stmt->execute([$status, $swapId]);
    }

    /**
     * Set the status column for one or more books inside an open transaction.
     */
    private static function setBooks(PDO $pdo, array $bookIds, string $status): void
    {
        $ids = array_values(array_unique(array_map('intval', $bookIds)));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE books SET status = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$status], $ids));
    }

    /**
     * List all swaps involving a user (with both book titles and user names), newest first.
     */
    public static function forUser(int $userId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT sr.*,
                    req.name AS requester_name,
                    own.name AS owner_name,
                    rb.title AS requested_book_title,
                    rb.image AS requested_book_image,
                    ob.title AS offered_book_title,
                    ob.image AS offered_book_image
             FROM swap_requests sr
             INNER JOIN users req ON sr.requester_id = req.id
             INNER JOIN users own ON sr.owner_id = own.id
             INNER JOIN books rb ON sr.requested_book_id = rb.id
             INNER JOIN books ob ON sr.offered_book_id = ob.id
             WHERE sr.requester_id = :u1 OR sr.owner_id = :u2
             ORDER BY sr.created_at DESC, sr.id DESC'
        );
        $stmt->execute([':u1' => $userId, ':u2' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Group a user's swaps into incoming, outgoing, and history lists for swaps.php.
     */
    public static function getUserSwapsGrouped(int $userId): array
    {
        $incoming = [];
        $outgoing = [];
        $history = [];

        foreach (self::forUser($userId) as $row) {
            $isActive = in_array($row['status'], ['pending', 'accepted'], true);

            if ($isActive) {
                if ((int) $row['owner_id'] === $userId) {
                    $incoming[] = $row;
                } else {
                    $outgoing[] = $row;
                }
            } else {
                $history[] = $row;
            }
        }

        return [
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'history'  => $history,
        ];
    }

    /**
     * Count incoming pending swap requests awaiting the given book owner's response.
     */
    public static function pendingIncoming(int $userId): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM swap_requests
             WHERE owner_id = ? AND status = 'pending'"
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count completed swaps for a given user.
     */
    public static function completedCount(int $userId): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM swap_requests
             WHERE status = 'completed' AND (requester_id = :u1 OR owner_id = :u2)"
        );
        $stmt->execute([':u1' => $userId, ':u2' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Return swap counts per status plus swaps completed in the last 7 days.
     */
    public static function stats(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) AS declined,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'completed' AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS completed_7d
             FROM swap_requests"
        );
        $row = $stmt->fetch();

        return [
            'pending'      => (int) ($row['pending'] ?? 0),
            'accepted'     => (int) ($row['accepted'] ?? 0),
            'declined'     => (int) ($row['declined'] ?? 0),
            'cancelled'    => (int) ($row['cancelled'] ?? 0),
            'completed'    => (int) ($row['completed'] ?? 0),
            'completed_7d' => (int) ($row['completed_7d'] ?? 0),
        ];
    }

    /**
     * Return true if the given book is currently part of an 'accepted' swap.
     */
    public static function bookInActiveSwap(int $bookId): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM swap_requests
             WHERE status = 'accepted'
               AND (requested_book_id = :b1 OR offered_book_id = :b2)
             LIMIT 1"
        );
        $stmt->execute([':b1' => $bookId, ':b2' => $bookId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * List recent swap requests across the platform for the admin dashboard.
     */
    public static function listRecentForAdmin(int $limit = 15): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT sr.id, sr.status, sr.requester_confirmed, sr.owner_confirmed,
                    sr.created_at, sr.completed_at,
                    rb.title AS requested_book_title,
                    ob.title AS offered_book_title,
                    req.name AS requester_name,
                    own.name AS owner_name
             FROM swap_requests sr
             INNER JOIN users req ON sr.requester_id = req.id
             INNER JOIN users own ON sr.owner_id = own.id
             INNER JOIN books rb ON sr.requested_book_id = rb.id
             INNER JOIN books ob ON sr.offered_book_id = ob.id
             ORDER BY sr.created_at DESC, sr.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
