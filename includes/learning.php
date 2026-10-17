<?php
const XP_LESSON         = 10;
const QUIZ_PASS_PERCENT = 70;

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
