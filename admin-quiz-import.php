<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/moderation.php';

require_login();
require_admin($conn);

const MAX_BANK = 60;

function parse_quiz_text(string $text, array &$errors): array
{
    $keys  = ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd', 'ANSWER' => 'answer', 'EXPLAIN' => 'explanation', 'EXPLANATION' => 'explanation'];
    $items = [];
    $cur   = null;
    $field = null;

    foreach (preg_split('/\R/', str_replace("\xEF\xBB\xBF", '', $text)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^(ANSWER|EXPLANATION|EXPLAIN|Q|A|B|C|D)\s*\d*\s*[:.)]\s*(.*)$/i', $line, $m)) {
            $key   = strtoupper($m[1]);
            $value = trim($m[2]);

            if ($key === 'Q') {
                if ($cur !== null) {
                    $items[] = $cur;
                }
                $cur   = ['question' => $value, 'a' => '', 'b' => '', 'c' => '', 'd' => '', 'answer' => '', 'explanation' => ''];
                $field = 'question';
                continue;
            }

            if ($cur === null) {
                $errors[] = 'Every question must start with a "Q:" line. Problem near: ' . mb_substr($line, 0, 40);
                return [];
            }

            $field = $keys[$key];
            if ($field !== 'explanation' && $cur[$field] !== '') {
                $errors[] = 'Question ' . (count($items) + 1) . ' has more than one "' . $key . ':" line.';
            }
            $cur[$field] = $value;
            continue;
        }

        if ($cur === null) {
            $errors[] = 'Every question must start with a "Q:" line. Problem near: ' . mb_substr($line, 0, 40);
            return [];
        }

        $cur[$field] = trim($cur[$field] . ' ' . $line);
    }

    if ($cur !== null) {
        $items[] = $cur;
    }

    return $items;
}

function check_quiz_items(array $items, array &$errors): array
{
    foreach ($items as $i => $it) {
        $n = $i + 1;

        if ($it['question'] === '' || mb_strlen($it['question']) > 500) {
            $errors[] = "Question $n: the question text is missing or longer than 500 characters.";
        }
        foreach (['a', 'b', 'c', 'd'] as $k) {
            if ($it[$k] === '' || mb_strlen($it[$k]) > 255) {
                $errors[] = "Question $n: option " . strtoupper($k) . ' is missing or longer than 255 characters.';
            }
        }

        $answer = strtoupper(substr(ltrim($it['answer']), 0, 1));
        if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) {
            $errors[] = "Question $n: ANSWER must be A, B, C or D.";
        }
        $items[$i]['answer'] = $answer;

        if (mb_strlen($it['explanation']) > 500) {
            $errors[] = "Question $n: the explanation is longer than 500 characters.";
        }
    }

    return $items;
}

$lessonId = (int) ($_GET['lesson'] ?? $_POST['lesson'] ?? 0);

$stmt = $conn->prepare('SELECT id, title, subject_slug FROM lessons WHERE id = ?');
$stmt->bind_param('i', $lessonId);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();

if (!$lesson) {
    $_SESSION['flash'] = 'That lesson could not be found.';
    header('Location: admin-lessons.php');
    exit;
}

$errors = [];
$text   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text    = (string) ($_POST['questions'] ?? '');
    $replace = ($_POST['mode'] ?? 'add') === 'replace';

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (trim($text) === '') {
        $errors[] = 'Paste your questions first.';
    } elseif (strlen($text) > 200000) {
        $errors[] = 'That is too much text. Import fewer questions at a time.';
    } else {
        $items = parse_quiz_text($text, $errors);
        if (!$errors) {
            if (!$items) {
                $errors[] = 'No questions found. Each one must start with "Q:".';
            } else {
                $items = check_quiz_items($items, $errors);
            }
        }
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT question, sort_order FROM quiz_questions WHERE lesson_id = ?');
        $stmt->bind_param('i', $lessonId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $seen      = [];
        $maxOrder  = 0;
        $baseCount = $replace ? 0 : count($rows);

        if (!$replace) {
            foreach ($rows as $row) {
                $seen[mb_strtolower($row['question'])] = true;
                $maxOrder = max($maxOrder, (int) $row['sort_order']);
            }
        }

        $fresh   = [];
        $skipped = 0;
        foreach ($items as $it) {
            $key = mb_strtolower($it['question']);
            if (isset($seen[$key])) {
                $skipped++;
                continue;
            }
            $seen[$key] = true;
            $fresh[]    = $it;
        }

        if (!$fresh) {
            $errors[] = 'Nothing to import: every question is already in the bank.';
        } elseif ($baseCount + count($fresh) > MAX_BANK) {
            $errors[] = 'A lesson can hold up to ' . MAX_BANK . ' questions. This import would bring it to '
                . ($baseCount + count($fresh)) . '.';
        } else {
            $conn->begin_transaction();
            try {
                if ($replace) {
                    $stmt = $conn->prepare('DELETE FROM quiz_questions WHERE lesson_id = ?');
                    $stmt->bind_param('i', $lessonId);
                    $stmt->execute();
                }

                $stmt = $conn->prepare(
                    'INSERT INTO quiz_questions
                     (lesson_id, question, option_a, option_b, option_c, option_d, correct_option, explanation, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('isssssssi', $lessonId, $q, $a, $b, $c, $d, $ans, $exp, $order);

                foreach ($fresh as $it) {
                    $q     = $it['question'];
                    $a     = $it['a'];
                    $b     = $it['b'];
                    $c     = $it['c'];
                    $d     = $it['d'];
                    $ans   = $it['answer'];
                    $exp   = $it['explanation'];
                    $order = ++$maxOrder;
                    $stmt->execute();
                }

                $conn->commit();

                $message = 'Imported ' . count($fresh) . ' question' . (count($fresh) === 1 ? '' : 's')
                    . ($skipped ? " ($skipped duplicate" . ($skipped === 1 ? '' : 's') . ' skipped)' : '')
                    . '. The bank now has ' . ($baseCount + count($fresh)) . ' questions.';
                $_SESSION['flash'] = $message;
                header('Location: admin-quiz-import.php?lesson=' . $lessonId);
                exit;
            } catch (Throwable $ex) {
                $conn->rollback();
                error_log('Genius Hub quiz import failed: ' . $ex->getMessage());
                $errors[] = 'Something went wrong while importing. Nothing was saved. Please try again.';
            }
        }
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$stmt = $conn->prepare('SELECT COUNT(*) FROM quiz_questions WHERE lesson_id = ?');
$stmt->bind_param('i', $lessonId);
$stmt->execute();
$bankCount = (int) $stmt->get_result()->fetch_row()[0];

$shown     = array_slice($errors, 0, 10);
$moreCount = count($errors) - count($shown);

$pageTitle = 'Import quiz questions';
$active    = 'admin';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="admin-lessons.php">Lessons</a></p>
            <h1>Import quiz questions</h1>
            <p><?= e($lesson['title']) ?> &middot; <?= $bankCount ?> question<?= $bankCount === 1 ? '' : 's' ?> in the bank</p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow stack">

            <?php if ($flash !== ''): ?>
                <div class="alert alert-success" role="status">
                    <?= e($flash) ?>
                    <a href="admin-lesson-edit.php?id=<?= $lessonId ?>">Review the questions</a>
                </div>
            <?php endif; ?>

            <?php if ($shown): ?>
                <div class="alert alert-error" role="alert">
                    <ul>
                        <?php foreach ($shown as $msg): ?>
                            <li><?= e($msg) ?></li>
                        <?php endforeach; ?>
                        <?php if ($moreCount > 0): ?>
                            <li>...and <?= $moreCount ?> more.</li>
                        <?php endif; ?>
                    </ul>
                    <p>Nothing was imported. Fix the problems and try again.</p>
                </div>
            <?php endif; ?>

            <div class="panel stack">
                <h2>Format</h2>
                <p class="muted">
                    Start each question with <code>Q:</code>, then options <code>A:</code> to <code>D:</code>
                    and the <code>ANSWER:</code> letter. <code>EXPLAIN:</code> is optional.
                    Blank lines between questions are fine.
                </p>
                <pre class="format-example">Q: What is the SI unit of force?
A: Joule
B: Newton
C: Watt
D: Pascal
ANSWER: B
EXPLAIN: Force is measured in newtons (N).

Q: Which of these is a vector quantity?
A: Speed
B: Distance
C: Displacement
D: Time
ANSWER: C</pre>
            </div>

            <form class="form panel" method="post" action="admin-quiz-import.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="lesson" value="<?= $lessonId ?>">

                <div class="field">
                    <label for="questions">Paste your questions</label>
                    <textarea id="questions" name="questions" rows="18" class="mono" required><?= e($text) ?></textarea>
                </div>

                <div class="field">
                    <label for="mode">What should happen to the questions already in this lesson?</label>
                    <select id="mode" name="mode">
                        <option value="add">Keep them and add these (duplicates are skipped)</option>
                        <option value="replace"<?= (($_POST['mode'] ?? '') === 'replace') ? ' selected' : '' ?>>Replace them with these</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Import questions</button>
                    <a class="btn btn-secondary" href="admin-lesson-edit.php?id=<?= $lessonId ?>">Back to the lesson</a>
                </div>
            </form>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
