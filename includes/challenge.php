<?php
require_once __DIR__ . '/xp.php';

const CHALLENGE_MIN_Q       = 5;
const CHALLENGE_MAX_Q       = 40;
const CHALLENGE_DAYS        = 3;
const CHALLENGE_GRACE       = 20;
const CHALLENGE_DAILY_LIMIT = 10;
const CHALLENGE_OPEN_LIMIT  = 3;
const XP_CHALLENGE          = 2;

function challenge_time_options(): array
{
    return [
        0  => 'No timer',
        5  => '5 minutes',
        10 => '10 minutes',
        15 => '15 minutes',
        20 => '20 minutes',
        30 => '30 minutes',
        45 => '45 minutes',
        60 => '60 minutes',
    ];
}

function format_seconds(int $seconds): string
{
    return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}

function load_questions_by_ids(mysqli $conn, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }

    $list = implode(',', $ids);
    $rows = $conn->query(
        "SELECT id, question, option_a, option_b, option_c, option_d, correct_option, explanation
         FROM quiz_questions WHERE id IN ($list)"
    )->fetch_all(MYSQLI_ASSOC);

    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }

    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }

    return $ordered;
}

function pick_challenge_questions(mysqli $conn, array $slugs, int $lessonId, int $count): array
{
    if ($lessonId > 0) {
        $stmt = $conn->prepare(
            'SELECT q.id FROM quiz_questions q
             JOIN lessons l ON l.id = q.lesson_id
             WHERE l.published = 1 AND l.id = ?
             ORDER BY RAND() LIMIT ?'
        );
        $stmt->bind_param('ii', $lessonId, $count);
    } else {
        $slugs = array_values($slugs);
        if (!$slugs) {
            return [];
        }

        $marks = implode(',', array_fill(0, count($slugs), '?'));
        $stmt  = $conn->prepare(
            "SELECT q.id FROM quiz_questions q
             JOIN lessons l ON l.id = q.lesson_id
             WHERE l.published = 1 AND l.subject_slug IN ($marks)
             ORDER BY RAND() LIMIT ?"
        );
        $types  = str_repeat('s', count($slugs)) . 'i';
        $params = array_merge($slugs, [$count]);
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    return array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
}

function grade_answers(array $questions, array $answers): array
{
    $score = 0;
    $given = [];

    foreach ($questions as $q) {
        $letter = $answers[$q['id']] ?? '';
        $letter = is_string($letter) ? strtoupper($letter) : '';
        if (!in_array($letter, ['A', 'B', 'C', 'D'], true)) {
            $letter = '';
        }

        $given[$q['id']] = $letter;
        if ($letter !== '' && $letter === $q['correct_option']) {
            $score++;
        }
    }

    return [$score, $given];
}

function expire_challenges(mysqli $conn): void
{
    $conn->query(
        "UPDATE challenges SET status = 'expired', completed_at = NOW()
         WHERE status = 'open' AND expires_at < NOW()"
    );
}

function submit_attempt(mysqli $conn, array $c, int $userId, array $answers, int $elapsed): bool
{
    $questions = load_questions_by_ids($conn, array_map('intval', explode(',', $c['question_ids'])));
    $limit     = (int) $c['time_limit_min'] * 60;

    if ($limit > 0 && $elapsed > $limit + CHALLENGE_GRACE) {
        $answers = [];
    }

    [$score, $given] = grade_answers($questions, $answers);
    $taken = $limit > 0 ? min($elapsed, $limit) : $elapsed;
    $json  = json_encode($given);
    $cid   = (int) $c['id'];

    $stmt = $conn->prepare(
        'UPDATE challenge_attempts SET submitted_at = NOW(), score = ?, time_taken = ?, answers = ?
         WHERE challenge_id = ? AND user_id = ? AND submitted_at IS NULL'
    );
    $stmt->bind_param('iisii', $score, $taken, $json, $cid, $userId);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        return false;
    }

    finalize_challenge($conn, $cid);

    return true;
}

function finalize_challenge(mysqli $conn, int $challengeId): void
{
    $stmt = $conn->prepare('SELECT status FROM challenges WHERE id = ?');
    $stmt->bind_param('i', $challengeId);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();

    if (!$c || $c['status'] !== 'open') {
        return;
    }

    $stmt = $conn->prepare(
        'SELECT user_id, score, time_taken FROM challenge_attempts
         WHERE challenge_id = ? AND submitted_at IS NOT NULL'
    );
    $stmt->bind_param('i', $challengeId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (count($rows) < 2) {
        return;
    }

    [$a, $b] = $rows;

    if ((int) $a['score'] !== (int) $b['score']) {
        $firstWins = (int) $a['score'] > (int) $b['score'];
    } elseif ((int) $a['time_taken'] !== (int) $b['time_taken']) {
        $firstWins = (int) $a['time_taken'] < (int) $b['time_taken'];
    } else {
        $firstWins = null;
    }

    $winner = $loser = null;
    if ($firstWins !== null) {
        $winner = (int) ($firstWins ? $a['user_id'] : $b['user_id']);
        $loser  = (int) ($firstWins ? $b['user_id'] : $a['user_id']);
    }

    $stmt = $conn->prepare(
        "UPDATE challenges SET status = 'done', winner_id = ?, completed_at = NOW()
         WHERE id = ? AND status = 'open'"
    );
    $stmt->bind_param('ii', $winner, $challengeId);
    $stmt->execute();

    if ($stmt->affected_rows !== 1 || $winner === null) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM challenges
         WHERE status = 'done' AND xp_awarded = 1 AND winner_id = ?
           AND (challenger_id = ? OR opponent_id = ?)
           AND completed_at > NOW() - INTERVAL 1 DAY"
    );
    $stmt->bind_param('iii', $winner, $loser, $loser);
    $stmt->execute();
    $recentWins = (int) $stmt->get_result()->fetch_row()[0];

    if ($recentWins === 0 && award_xp($conn, $winner, XP_CHALLENGE, 'Won a quiz challenge') > 0) {
        $stmt = $conn->prepare('UPDATE challenges SET xp_awarded = 1 WHERE id = ?');
        $stmt->bind_param('i', $challengeId);
        $stmt->execute();
    }
}
