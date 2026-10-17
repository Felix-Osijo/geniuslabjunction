<?php
const REPORT_REASONS = [
    'spam'          => 'Spam',
    'abuse'         => 'Abuse or harassment',
    'inappropriate' => 'Inappropriate content',
    'other'         => 'Other',
];

function user_role(mysqli $conn, int $userId): string
{
    $stmt = $conn->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row['role'] ?? 'user';
}

function require_admin(mysqli $conn): void
{
    if (user_role($conn, (int) ($_SESSION['user_id'] ?? 0)) !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}

function owner_of(mysqli $conn, string $type, int $id): ?int
{
    $table = $type === 'comment' ? 'comments' : 'posts';
    $stmt  = $conn->prepare("SELECT user_id FROM $table WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ? (int) $row['user_id'] : null;
}

function delete_post(mysqli $conn, int $postId): void
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "DELETE FROM reports WHERE target_type = 'comment'
             AND target_id IN (SELECT id FROM comments WHERE post_id = ?)"
        );
        $stmt->bind_param('i', $postId);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM reports WHERE target_type = 'post' AND target_id = ?");
        $stmt->bind_param('i', $postId);
        $stmt->execute();

        $stmt = $conn->prepare('DELETE FROM comments WHERE post_id = ?');
        $stmt->bind_param('i', $postId);
        $stmt->execute();

        $stmt = $conn->prepare('DELETE FROM likes WHERE post_id = ?');
        $stmt->bind_param('i', $postId);
        $stmt->execute();

        $stmt = $conn->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->bind_param('i', $postId);
        $stmt->execute();

        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }
}

function delete_comment(mysqli $conn, int $commentId): void
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM reports WHERE target_type = 'comment' AND target_id = ?");
        $stmt->bind_param('i', $commentId);
        $stmt->execute();

        $stmt = $conn->prepare('DELETE FROM comments WHERE id = ?');
        $stmt->bind_param('i', $commentId);
        $stmt->execute();

        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }
}
