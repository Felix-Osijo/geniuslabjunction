<?php
const XP_LESSON         = 10;
const QUIZ_PASS_PERCENT = 70;
const QUIZ_SIZE         = 10;

function render_inline(string $text): string
{
    $html = e($text);

    $html = preg_replace('/\*\*(?!\s)(.+?)(?<!\s)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(?!\s)(.+?)(?<!\s)\*/s', '<em>$1</em>', $html);
    $html = preg_replace('/\+\+(?!\s)(.+?)(?<!\s)\+\+/s', '<u>$1</u>', $html);
    $html = preg_replace('/==(?!\s)(.+?)(?<!\s)==/s', '<mark>$1</mark>', $html);
    $html = preg_replace('/\^\{(.+?)\}/', '<sup>$1</sup>', $html);
    $html = preg_replace('/_\{(.+?)\}/', '<sub>$1</sub>', $html);

    return $html;
}

function render_lesson_body(string $body): string
{
    $html = '';

    foreach (preg_split('/\R{2,}/', trim($body)) as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }

        $lines = preg_split('/\R/', $block);

        if (preg_match('/^(##|###) (.+)$/', $lines[0], $m)) {
            $tag   = strlen($m[1]) === 2 ? 'h2' : 'h3';
            $html .= "<$tag>" . render_inline($m[2]) . "</$tag>";
            array_shift($lines);
        }

        if (!$lines) {
            continue;
        }

        $bullets = count(array_filter($lines, fn($l) => strpos($l, '- ') === 0));
        $numbers = count(array_filter($lines, fn($l) => preg_match('/^\d+\.\s/', $l) === 1));

        if ($bullets === count($lines)) {
            $html .= '<ul>';
            foreach ($lines as $line) {
                $html .= '<li>' . render_inline(substr($line, 2)) . '</li>';
            }
            $html .= '</ul>';
        } elseif ($numbers === count($lines)) {
            $html .= '<ol>';
            foreach ($lines as $line) {
                $html .= '<li>' . render_inline(preg_replace('/^\d+\.\s/', '', $line)) . '</li>';
            }
            $html .= '</ol>';
        } else {
            $html .= '<p>' . nl2br(render_inline(implode("\n", $lines))) . '</p>';
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
