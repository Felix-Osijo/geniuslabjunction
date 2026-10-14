<?php
const XP_PER_LEVEL = 100;
const XP_DAILY_CAP = 50;
const XP_POST      = 5;
const XP_COMMENT   = 2;

function award_xp(mysqli $conn, int $userId, int $amount, string $reason): int
{
    $stmt = $conn->prepare(
        'SELECT COALESCE(SUM(xp_earned), 0) AS total
         FROM xp_logs
         WHERE user_id = ? AND created_at >= CURDATE()'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $earnedToday = (int) $stmt->get_result()->fetch_assoc()['total'];

    $amount = min($amount, XP_DAILY_CAP - $earnedToday);
    if ($amount <= 0) {
        return 0;
    }

    $perLevel = XP_PER_LEVEL;

    $conn->begin_transaction();
    try {
        $log = $conn->prepare('INSERT INTO xp_logs (user_id, xp_earned, reason) VALUES (?, ?, ?)');
        $log->bind_param('iis', $userId, $amount, $reason);
        $log->execute();

        $upd = $conn->prepare('UPDATE users SET level = (xp + ?) DIV ? + 1, xp = xp + ? WHERE id = ?');
        $upd->bind_param('iiii', $amount, $perLevel, $amount, $userId);
        $upd->execute();

        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }

    return $amount;
}
