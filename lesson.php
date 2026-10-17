<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/xp.php';
require __DIR__ . '/includes/learning.php';

$subjects    = require __DIR__ . '/includes/subjects.php';
$subjectSlug = (string) ($_GET['subject'] ?? '');
$lessonSlug  = (string) ($_GET['lesson'] ?? '');

function lesson_not_found(): void
{
    http_response_code(404);
    $pageTitle = 'Lesson not found';
    require __DIR__ . '/includes/header.php';
    ?>
    <main>
        <section class="section">
            <div class="container narrow">
                <div class="panel empty">
                    <h2>Lesson not found</h2>
                    <p>That lesson does not exist or is not published.</p>
                    <a class="btn btn-primary" href="homepage.php#subjects">Browse subjects</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

if (!isset($subjects[$subjectSlug])) {
    lesson_not_found();
}

$stmt = $conn->prepare(
    'SELECT id, title, summary, body FROM lessons
     WHERE subject_slug = ? AND slug = ? AND published = 1'
);
$stmt->bind_param('ss', $subjectSlug, $lessonSlug);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();

if (!$lesson) {
    lesson_not_found();
}

$lessonId    = (int) $lesson['id'];
$userId      = (int) ($_SESSION['user_id'] ?? 0);
$subjectName = $subjects[$subjectSlug];
$selfUrl     = 'lesson.php?subject=' . urlencode($subjectSlug) . '&lesson=' . urlencode($lessonSlug);
$optionCols  = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];

$stmt = $conn->prepare(
    'SELECT id, question, option_a, option_b, option_c, option_d, correct_option, explanation
     FROM quiz_questions WHERE lesson_id = ? ORDER BY sort_order, id'
);
$stmt->bind_param('i', $lessonId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$errors = [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $questions) {
    $answers = $_POST['answer'] ?? [];
    $answers = is_array($answers) ? $answers : [];

    if (!is_logged_in()) {
        $errors[] = 'Log in to take the quiz.';
    } elseif (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $correct = 0;
        $review  = [];
        $missing = false;

        foreach ($questions as $q) {
            $given = $answers[$q['id']] ?? '';
            $given = is_string($given) ? strtoupper($given) : '';

            if (!isset($optionCols[$given])) {
                $missing = true;
                break;
            }

            $right = $given === $q['correct_option'];
            $correct += $right ? 1 : 0;
            $review[$q['id']] = ['given' => $given, 'right' => $right];
        }

        if ($missing) {
            $errors[] = 'Answer every question before submitting.';
        } else {
            $total   = count($questions);
            $percent = (int) round($correct / $total * 100);
            $passed  = $percent >= QUIZ_PASS_PERCENT;

            if ($passed) {
                $stmt = $conn->prepare(
                    'INSERT IGNORE INTO lesson_completions (user_id, lesson_id, best_score) VALUES (?, ?, ?)'
                );
                $stmt->bind_param('iii', $userId, $lessonId, $percent);
                $stmt->execute();

                if ($stmt->affected_rows === 1) {
                    $reason = 'Completed lesson: ' . mb_substr($lesson['title'], 0, 60);
                    $earned = award_xp($conn, $userId, XP_LESSON, $reason);
                    $message = $earned > 0
                        ? "Lesson complete! +$earned XP."
                        : 'Lesson complete! You have reached today\'s XP limit, so no XP this time.';
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE lesson_completions SET best_score = GREATEST(best_score, ?)
                         WHERE user_id = ? AND lesson_id = ?'
                    );
                    $stmt->bind_param('iii', $percent, $userId, $lessonId);
                    $stmt->execute();
                    $message = 'You passed again. XP is only awarded the first time.';
                }
            } else {
                $message = 'Not quite. Read the lesson again and give it another try.';
            }

            $result = [
                'correct' => $correct,
                'total'   => $total,
                'percent' => $percent,
                'passed'  => $passed,
                'message' => $message,
                'review'  => $review,
            ];
        }
    }
}

$best = null;
if ($userId) {
    $stmt = $conn->prepare('SELECT best_score FROM lesson_completions WHERE user_id = ? AND lesson_id = ?');
    $stmt->bind_param('ii', $userId, $lessonId);
    $stmt->execute();
    $row  = $stmt->get_result()->fetch_assoc();
    $best = $row ? (int) $row['best_score'] : null;
}

$stmt = $conn->prepare(
    'SELECT slug, title FROM lessons WHERE subject_slug = ? AND published = 1 ORDER BY sort_order, id'
);
$stmt->bind_param('s', $subjectSlug);
$stmt->execute();
$siblings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$prev = $next = null;
foreach ($siblings as $i => $s) {
    if ($s['slug'] === $lessonSlug) {
        $prev = $siblings[$i - 1] ?? null;
        $next = $siblings[$i + 1] ?? null;
    }
}

$pageTitle = $lesson['title'];
$active    = 'home';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="subjects/<?= e($subjectSlug) ?>.php"><?= e($subjectName) ?></a></p>
            <h1><?= e($lesson['title']) ?></h1>
            <p><?= e($lesson['summary']) ?></p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow stack">

            <?php if ($best !== null && !$result): ?>
                <div class="alert alert-success" role="status">You completed this lesson. Best score: <?= $best ?>%.</div>
            <?php endif; ?>

            <article class="prose lesson-body"><?= render_lesson_body($lesson['body']) ?></article>

            <?php if ($questions): ?>
                <div class="panel quiz" id="quiz">
                    <h2>Quick quiz</h2>

                    <?php if ($errors): ?>
                        <div class="alert alert-error" role="alert">
                            <ul>
                                <?php foreach ($errors as $msg): ?>
                                    <li><?= e($msg) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($result): ?>
                        <p class="score"><?= $result['correct'] ?> / <?= $result['total'] ?> (<?= $result['percent'] ?>%)</p>
                        <div class="alert <?= $result['passed'] ? 'alert-success' : 'alert-info' ?>" role="status">
                            <?= e($result['message']) ?>
                        </div>

                        <?php foreach ($questions as $i => $q): ?>
                            <?php $r = $result['review'][$q['id']]; ?>
                            <div class="quiz-q">
                                <p class="quiz-title"><?= $i + 1 ?>. <?= e($q['question']) ?></p>
                                <?php foreach ($optionCols as $letter => $col): ?>
                                    <?php
                                    $cls = '';
                                    if ($letter === $q['correct_option']) {
                                        $cls = ' correct';
                                    } elseif ($letter === $r['given']) {
                                        $cls = ' wrong';
                                    }
                                    ?>
                                    <div class="quiz-option<?= $cls ?>"><strong><?= $letter ?>.</strong> <span><?= e($q[$col]) ?></span></div>
                                <?php endforeach; ?>
                                <?php if ($q['explanation'] !== ''): ?>
                                    <p class="explain"><?= e($q['explanation']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <a class="btn btn-secondary" href="<?= e($selfUrl) ?>#quiz">Retake quiz</a>

                    <?php elseif (is_logged_in()): ?>
                        <form method="post" action="<?= e($selfUrl) ?>#quiz" class="quiz">
                            <?= csrf_field() ?>
                            <?php foreach ($questions as $i => $q): ?>
                                <fieldset class="quiz-q">
                                    <legend><?= $i + 1 ?>. <?= e($q['question']) ?></legend>
                                    <?php foreach ($optionCols as $letter => $col): ?>
                                        <label class="quiz-option">
                                            <input type="radio" name="answer[<?= (int) $q['id'] ?>]" value="<?= $letter ?>" required>
                                            <span><strong><?= $letter ?>.</strong> <?= e($q[$col]) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary">Submit answers</button>
                        </form>

                    <?php else: ?>
                        <p class="muted">Log in to take the quiz and earn XP.</p>
                        <div class="hero-actions">
                            <a class="btn btn-primary" href="login.php">Log in</a>
                            <a class="btn btn-secondary" href="register.php">Create account</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <nav class="lesson-nav" aria-label="Lessons">
                <?php if ($prev): ?>
                    <a class="btn btn-secondary btn-sm" href="lesson.php?subject=<?= e(urlencode($subjectSlug)) ?>&amp;lesson=<?= e(urlencode($prev['slug'])) ?>">&larr; <?= e($prev['title']) ?></a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <?php if ($next): ?>
                    <a class="btn btn-primary btn-sm" href="lesson.php?subject=<?= e(urlencode($subjectSlug)) ?>&amp;lesson=<?= e(urlencode($next['slug'])) ?>">Next: <?= e($next['title']) ?> &rarr;</a>
                <?php endif; ?>
            </nav>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
