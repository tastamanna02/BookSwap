<?php
/**
 * Swap Model
 *
 * Manages the swap_requests lifecycle:
 * pending -> accepted -> completed (when BOTH users confirm), or declined / cancelled.
 * Uses database transactions and ordered row locks (FOR UPDATE) for multi-row updates.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

class Swap
{
    /**
     * Lock two book rows in ascending ID order to prevent deadlocks.
     */
    private static function lockBooksOrdered(PDO $pdo, int $bookIdA, int $bookIdB): array
    {
        $firstId = min($bookIdA, $bookIdB);
        $secondId = max($bookIdA, $bookIdB);

        $stmt = $pdo->prepare(
            'SELECT id, owner_id, title, status
             FROM books
             WHERE id IN (?, ?)
             ORDER BY id ASC
             FOR UPDATE'
        );
        $stmt->execute([$firstId, $secondId]);

        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $byId[(int) $row['id']] = $row;
        }
        return $byId;
    }

    /**
     * Create a new 'pending' swap request offering one of the requester's available books.
     */
    public static function createRequest(int $requesterId, int $requestedBookId, int $offeredBookId): array
    {
        if ($requestedBookId <= 0 || $offeredBookId <= 0) {
            return ['ok' => false, 'message' => 'Please select valid books for the swap.'];
        }

        if ($requestedBookId === $offeredBookId) {
            return ['ok' => false, 'message' => 'You cannot swap a book with itself.'];
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $books = self::lockBooksOrdered($pdo, $requestedBookId, $offeredBookId);

            if (!isset($books[$requestedBookId], $books[$offeredBookId])) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'One of the selected books is no longer available.'];
            }

            $requestedBook = $books[$requestedBookId];
            $offeredBook = $books[$offeredBookId];

            if ((int) $offeredBook['owner_id'] !== $requesterId) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'You can only offer a book that you own.'];
            }

            if ((int) $requestedBook['owner_id'] === $requesterId) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'You cannot request a swap for your own book.'];
            }

            if ($requestedBook['status'] !== 'available' || $offeredBook['status'] !== 'available') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Both books must be available to start a swap.'];
            }

            // Prevent duplicate pending or accepted requests for the exact same book pair
            $dupStmt = $pdo->prepare(
                "SELECT id FROM swap_requests
                 WHERE requester_id = ? AND requested_book_id = ? AND offered_book_id = ?
                   AND status IN ('pending', 'accepted')
                 LIMIT 1"
            );
            $dupStmt->execute([$requesterId, $requestedBookId, $offeredBookId]);
            if ($dupStmt->fetchColumn() !== false) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'You already have an active request for this book pair.'];
            }

            $insert = $pdo->prepare(
                "INSERT INTO swap_requests
                 (requester_id, owner_id, requested_book_id, offered_book_id, status)
                 VALUES (?, ?, ?, ?, 'pending')"
            );
            $insert->execute([
                $requesterId,
                (int) $requestedBook['owner_id'],
                $requestedBookId,
                $offeredBookId,
            ]);

            $swapId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return [
                'ok'      => true,
                'swap_id' => $swapId,
                'message' => 'Swap request sent! You can track it on the My Swaps page.',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Dispatch a swap state transition ('accept', 'decline', 'cancel', or 'confirm')
     * inside a database transaction with server-side participant checks.
     */
    public static function handleAction(int $swapId, int $userId, string $action): array
    {
        if (!in_array($action, ['accept', 'decline', 'cancel', 'confirm'], true)) {
            return ['ok' => false, 'message' => 'Invalid swap action.'];
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM swap_requests WHERE id = ? FOR UPDATE');
            $stmt->execute([$swapId]);
            $swap = $stmt->fetch();

            if ($swap === false) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Swap request not found.'];
            }

            $isRequester = ((int) $swap['requester_id'] === $userId);
            $isOwner = ((int) $swap['owner_id'] === $userId);

            if (!$isRequester && !$isOwner) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'You do not have permission to modify this swap.'];
            }

            switch ($action) {
                case 'accept':
                    $result = self::acceptSwap($pdo, $swap, $isOwner);
                    break;
                case 'decline':
                    $result = self::declineSwap($pdo, $swap, $isOwner);
                    break;
                case 'cancel':
                    $result = self::cancelSwap($pdo, $swap, $isRequester);
                    break;
                case 'confirm':
                    $result = self::confirmSwap($pdo, $swap, $isRequester);
                    break;
            }

            if (!$result['ok']) {
                $pdo->rollBack();
                return $result;
            }

            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Accept a pending swap: reserve both books and decline competing pending requests.
     */
    private static function acceptSwap(PDO $pdo, array $swap, bool $isOwner): array
    {
        if (!$isOwner) {
            return ['ok' => false, 'message' => 'Only the book owner can accept this request.'];
        }

        if ($swap['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending requests can be accepted.'];
        }

        $reqBookId = (int) $swap['requested_book_id'];
        $offBookId = (int) $swap['offered_book_id'];

        $books = self::lockBooksOrdered($pdo, $reqBookId, $offBookId);
        if (!isset($books[$reqBookId], $books[$offBookId])) {
            return ['ok' => false, 'message' => 'One of the books in this swap no longer exists.'];
        }

        if ($books[$reqBookId]['status'] !== 'available' || $books[$offBookId]['status'] !== 'available') {
            return ['ok' => false, 'message' => 'One of these books is already reserved.'];
        }

        $pdo->prepare("UPDATE swap_requests SET status = 'accepted' WHERE id = ?")
            ->execute([(int) $swap['id']]);

        // Reserve both books so neither can be double-booked during handoff
        $pdo->prepare("UPDATE books SET status = 'reserved' WHERE id IN (?, ?)")
            ->execute([$reqBookId, $offBookId]);

        // Auto-decline any competing pending requests involving either reserved book
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
            ':swap_id' => (int) $swap['id'],
            ':r1'      => $reqBookId,
            ':r2'      => $offBookId,
            ':o1'      => $reqBookId,
            ':o2'      => $offBookId,
        ]);

        return [
            'ok'      => true,
            'message' => 'Swap accepted! Both users must confirm once the books are exchanged.',
        ];
    }

    /**
     * Decline a pending swap (owner only).
     */
    private static function declineSwap(PDO $pdo, array $swap, bool $isOwner): array
    {
        if (!$isOwner) {
            return ['ok' => false, 'message' => 'Only the book owner can decline this request.'];
        }

        if ($swap['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending requests can be declined.'];
        }

        $pdo->prepare("UPDATE swap_requests SET status = 'declined' WHERE id = ?")
            ->execute([(int) $swap['id']]);

        return ['ok' => true, 'message' => 'Swap request declined.'];
    }

    /**
     * Cancel a pending (requester only) or accepted (either participant) swap.
     */
    private static function cancelSwap(PDO $pdo, array $swap, bool $isRequester): array
    {
        if ($swap['status'] === 'pending') {
            if (!$isRequester) {
                return ['ok' => false, 'message' => 'Use Decline to reject an incoming pending request.'];
            }

            $pdo->prepare("UPDATE swap_requests SET status = 'cancelled' WHERE id = ?")
                ->execute([(int) $swap['id']]);

            return ['ok' => true, 'message' => 'Swap request cancelled.'];
        }

        if ($swap['status'] === 'accepted') {
            $pdo->prepare("UPDATE swap_requests SET status = 'cancelled' WHERE id = ?")
                ->execute([(int) $swap['id']]);

            $reqBookId = (int) $swap['requested_book_id'];
            $offBookId = (int) $swap['offered_book_id'];

            self::lockBooksOrdered($pdo, $reqBookId, $offBookId);
            $pdo->prepare(
                "UPDATE books SET status = 'available' WHERE status = 'reserved' AND id IN (?, ?)"
            )->execute([$reqBookId, $offBookId]);

            return ['ok' => true, 'message' => 'Accepted swap cancelled. Both books are available again.'];
        }

        return ['ok' => false, 'message' => 'This swap can no longer be cancelled.'];
    }

    /**
     * Record a participant's confirmation. Only completes the swap once BOTH
     * requester_confirmed and owner_confirmed equal 1.
     */
    private static function confirmSwap(PDO $pdo, array $swap, bool $isRequester): array
    {
        if ($swap['status'] !== 'accepted') {
            return ['ok' => false, 'message' => 'Only accepted swaps can be confirmed as completed.'];
        }

        $reqConfirmed = (int) $swap['requester_confirmed'];
        $ownerConfirmed = (int) $swap['owner_confirmed'];

        if ($isRequester) {
            if ($reqConfirmed === 1) {
                return ['ok' => false, 'message' => 'You have already confirmed this swap.'];
            }
            $reqConfirmed = 1;
            $pdo->prepare('UPDATE swap_requests SET requester_confirmed = 1 WHERE id = ?')
                ->execute([(int) $swap['id']]);
        } else {
            if ($ownerConfirmed === 1) {
                return ['ok' => false, 'message' => 'You have already confirmed this swap.'];
            }
            $ownerConfirmed = 1;
            $pdo->prepare('UPDATE swap_requests SET owner_confirmed = 1 WHERE id = ?')
                ->execute([(int) $swap['id']]);
        }

        // Complete the swap only when BOTH users have confirmed
        if ($reqConfirmed === 1 && $ownerConfirmed === 1) {
            $pdo->prepare(
                "UPDATE swap_requests SET status = 'completed', completed_at = NOW() WHERE id = ?"
            )->execute([(int) $swap['id']]);

            return [
                'ok'        => true,
                'completed' => true,
                'message'   => 'Both students confirmed! Swap completed and added to your history.',
            ];
        }

        return [
            'ok'        => true,
            'completed' => false,
            'message'   => 'Confirmation saved! Waiting for the other student to confirm.',
        ];
    }

    /**
     * Fetch all swaps involving a user, grouped into incoming, outgoing, and history.
     */
    public static function getUserSwapsGrouped(int $userId): array
    {
        $pdo = Database::getConnection();
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
             ORDER BY sr.updated_at DESC, sr.id DESC'
        );
        $stmt->execute([':u1' => $userId, ':u2' => $userId]);

        $incoming = [];
        $outgoing = [];
        $history = [];

        foreach ($stmt->fetchAll() as $row) {
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
     * Count completed swaps for a given user.
     */
    public static function countCompletedForUser(int $userId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM swap_requests
             WHERE status = 'completed' AND (requester_id = :u1 OR owner_id = :u2)"
        );
        $stmt->execute([':u1' => $userId, ':u2' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Return aggregate swap counts for the admin dashboard.
     */
    public static function getStatsForAdmin(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_swaps,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_swaps,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_swaps
             FROM swap_requests"
        );
        $row = $stmt->fetch();

        return [
            'pending_swaps'   => (int) ($row['pending_swaps'] ?? 0),
            'accepted_swaps'  => (int) ($row['accepted_swaps'] ?? 0),
            'completed_swaps' => (int) ($row['completed_swaps'] ?? 0),
        ];
    }

    /**
     * List recent swap requests across the platform for the admin dashboard.
     */
    public static function listRecentForAdmin(int $limit = 15): array
    {
        $pdo = Database::getConnection();
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
