<?php
const XP_LESSON         = 10;
const QUIZ_PASS_PERCENT = 70;
const QUIZ_SIZE         = 10;

function render_lesson_body(string $body): string
{
    $html = '';

    foreach (preg_split('/\R{2,}/', trim($body)) as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }

        if (str_starts_with($block, '## ')) {
            $parts = preg_split('/\R/', $block, 2);
            $html .= '<h2>' . e(substr($parts[0], 3)) . '</h2>';
            $block = trim($parts[1] ?? '');
            if ($block === '') {
                continue;
            }
        }

        $lines  = preg_split('/\R/', $block);
        $bullets = array_filter($lines, fn($line) => str_starts_with($line, '- '));

        if (count($bullets) === count($lines)) {
            $html .= '<ul>';
            foreach ($lines as $line) {
                $html .= '<li>' . e(substr($line, 2)) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . nl2br(e($block)) . '</p>';
        }
    }

    return $html;
}

function count_quiz_bank(mysqli $conn, int $lessonId): int
{
    $stmt = $conn->prepare('SELECT COUNT(*) FROM quiz_questions WHERE lesson_id = ?');
    $stmt->bind_param('i', $lessonId);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_row()[0];
}

function pick_quiz_ids(mysqli $conn, int $lessonId): array
{
    $limit = QUIZ_SIZE;
    $stmt  = $conn->prepare('SELECT id FROM quiz_questions WHERE lesson_id = ? ORDER BY RAND() LIMIT ?');
    $stmt->bind_param('ii', $lessonId, $limit);
    $stmt->execute();

    return array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
}

function load_quiz_questions(mysqli $conn, int $lessonId, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }

    $list = implode(',', $ids);
    $rows = $conn->query(
        "SELECT id, question, option_a, option_b, option_c, option_d, correct_option, explanation
         FROM quiz_questions
         WHERE lesson_id = $lessonId AND id IN ($list)"
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
