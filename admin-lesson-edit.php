<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/moderation.php';
require_once __DIR__ . '/includes/learning.php';

require_login();
require_admin($conn);

const MAX_QUESTIONS = 60;

function post_str(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function blank_question(): array
{
    return [
        'id' => 0, 'question' => '',
        'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '',
        'correct_option' => 'A', 'explanation' => '',
    ];
}

$subjects = [];
foreach ($conn->query('SELECT slug, name FROM subjects ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC) as $row) {
    $subjects[$row['slug']] = $row['name'];
}
$letters  = ['A', 'B', 'C', 'D'];
$id       = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$errors   = [];
$questions = [];

$wanted = $_GET['subject'] ?? '';
$wanted = is_string($wanted) ? $wanted : '';

$lesson = [
    'subject_slug' => isset($subjects[$wanted]) ? $wanted : array_key_first($subjects),
    'slug'         => '',
    'title'        => '',
    'summary'      => '',
    'body'         => '',
    'sort_order'   => 0,
    'published'    => 1,
];

if ($id > 0) {
    $stmt = $conn->prepare(
        'SELECT subject_slug, slug, title, summary, body, sort_order, published FROM lessons WHERE id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();

    if (!$found) {
        $_SESSION['flash'] = 'That lesson could not be found.';
        header('Location: admin-lessons.php');
        exit;
    }
    $lesson = $found;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $stmt = $conn->prepare(
            'SELECT id, question, option_a, option_b, option_c, option_d, correct_option, explanation
             FROM quiz_questions WHERE lesson_id = ? ORDER BY sort_order, id'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $subject   = post_str('subject');
    $title     = post_str('title');
    $slug      = strtolower(post_str('slug'));
    $summary   = post_str('summary');
    $body      = post_str('body');
    $sortOrder = (int) post_str('sort_order');
    $published = isset($_POST['published']) ? 1 : 0;

    if ($slug === '') {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
        $slug = trim(substr($slug, 0, 80), '-');
    }

    $lesson = [
        'subject_slug' => $subject, 'slug' => $slug, 'title' => $title, 'summary' => $summary,
        'body' => $body, 'sort_order' => $sortOrder, 'published' => $published,
    ];

    if (!isset($subjects[$subject])) {
        $errors[] = 'Choose a subject.';
    }
    if ($title === '' || mb_strlen($title) > 150) {
        $errors[] = 'Title is required (up to 150 characters).';
    }
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) > 80) {
        $errors[] = 'URL name must use lowercase letters, numbers and hyphens (up to 80 characters).';
    }
    if (mb_strlen($summary) > 255) {
        $errors[] = 'Summary can be up to 255 characters.';
    }
    if ($body === '' || strlen($body) > 60000) {
        $errors[] = 'Lesson body is required and must be under 60,000 characters.';
    }
    if ($sortOrder < 0 || $sortOrder > 9999) {
        $errors[] = 'Order must be between 0 and 9999.';
    }

    $posted = $_POST['q'] ?? [];
    $posted = is_array($posted) ? array_slice(array_values($posted), 0, MAX_QUESTIONS) : [];
    $keep      = [];
    $removeIds = [];

    foreach ($posted as $i => $row) {
        if (!is_array($row)) {
            continue;
        }

        $qid  = (int) ($row['id'] ?? 0);
        $item = [
            'id'             => $qid,
            'question'       => trim((string) ($row['question'] ?? '')),
            'option_a'       => trim((string) ($row['a'] ?? '')),
            'option_b'       => trim((string) ($row['b'] ?? '')),
            'option_c'       => trim((string) ($row['c'] ?? '')),
            'option_d'       => trim((string) ($row['d'] ?? '')),
            'correct_option' => strtoupper(trim((string) ($row['correct'] ?? ''))),
            'explanation'    => trim((string) ($row['explanation'] ?? '')),
        ];

        if ($item['question'] === '') {
            if ($qid > 0) {
                $removeIds[] = $qid;
                $questions[] = $item;
            }
            continue;
        }

        $n = $i + 1;
        if (mb_strlen($item['question']) > 500) {
            $errors[] = "Question $n is too long (up to 500 characters).";
        }
        foreach (['option_a', 'option_b', 'option_c', 'option_d'] as $col) {
            if ($item[$col] === '' || mb_strlen($item[$col]) > 255) {
                $errors[] = "Question $n: fill in all four options (up to 255 characters each).";
                break;
            }
        }
        if (!in_array($item['correct_option'], $letters, true)) {
            $errors[] = "Question $n: choose the correct answer.";
        }
        if (mb_strlen($item['explanation']) > 500) {
            $errors[] = "Question $n: explanation can be up to 500 characters.";
        }

        $keep[]      = $item;
        $questions[] = $item;
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            if ($id > 0) {
                $stmt = $conn->prepare(
                    'UPDATE lessons SET subject_slug = ?, slug = ?, title = ?, summary = ?, body = ?,
                     sort_order = ?, published = ? WHERE id = ?'
                );
                $stmt->bind_param('sssssiii', $subject, $slug, $title, $summary, $body, $sortOrder, $published, $id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO lessons (subject_slug, slug, title, summary, body, sort_order, published)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('sssssii', $subject, $slug, $title, $summary, $body, $sortOrder, $published);
                $stmt->execute();
                $id = (int) $stmt->insert_id;
            }

            foreach ($removeIds as $qid) {
                $stmt = $conn->prepare('DELETE FROM quiz_questions WHERE id = ? AND lesson_id = ?');
                $stmt->bind_param('ii', $qid, $id);
                $stmt->execute();
            }

            foreach ($keep as $pos => $q) {
                $order = $pos + 1;

                if ($q['id'] > 0) {
                    $stmt = $conn->prepare(
                        'UPDATE quiz_questions SET question = ?, option_a = ?, option_b = ?, option_c = ?,
                         option_d = ?, correct_option = ?, explanation = ?, sort_order = ?
                         WHERE id = ? AND lesson_id = ?'
                    );
                    $stmt->bind_param(
                        'sssssssiii',
                        $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'],
                        $q['correct_option'], $q['explanation'], $order, $q['id'], $id
                    );
                } else {
                    $stmt = $conn->prepare(
                        'INSERT INTO quiz_questions
                         (lesson_id, question, option_a, option_b, option_c, option_d, correct_option, explanation, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->bind_param(
                        'isssssssi',
                        $id, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'],
                        $q['correct_option'], $q['explanation'], $order
                    );
                }
                $stmt->execute();
            }

            $conn->commit();
            $_SESSION['flash'] = 'Lesson saved.';
            header('Location: admin-lessons.php');
            exit;
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            if ((int) $ex->getCode() === 1062) {
                $errors[] = 'That URL name is already used by another lesson in this subject.';
            } else {
                error_log('Genius Hub lesson save failed: ' . $ex->getMessage());
                $errors[] = 'Something went wrong while saving. Please try again.';
            }
        }
    }
}

$slots = min(MAX_QUESTIONS, count($questions) + 4);

$pageTitle = $id > 0 ? 'Edit lesson' : 'New lesson';
$active    = 'admin';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="admin-lessons.php">Lessons</a></p>
            <h1><?= $id > 0 ? 'Edit lesson' : 'New lesson' ?></h1>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow">
            <form class="form" method="post" action="admin-lesson-edit.php" novalidate>
                <?php if ($errors): ?>
                    <div class="alert alert-error" role="alert">
                        <ul>
                            <?php foreach ($errors as $msg): ?>
                                <li><?= e($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="panel stack">
                    <h2>Lesson</h2>

                    <div class="field-row">
                        <div class="field">
                            <label for="subject">Subject</label>
                            <select id="subject" name="subject" required>
                                <?php foreach ($subjects as $slugKey => $name): ?>
                                    <option value="<?= e($slugKey) ?>"<?= $lesson['subject_slug'] === $slugKey ? ' selected' : '' ?>><?= e($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="sort_order">Order in the subject</label>
                            <input type="number" id="sort_order" name="sort_order" min="0" max="9999"
                                   value="<?= (int) $lesson['sort_order'] ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" maxlength="150"
                               value="<?= e($lesson['title']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="slug">URL name</label>
                        <input type="text" id="slug" name="slug" maxlength="80"
                               value="<?= e($lesson['slug']) ?>" placeholder="Leave blank to create it from the title">
                        <small class="hint">Lowercase letters, numbers and hyphens. Changing it later changes the lesson's link.</small>
                    </div>

                    <div class="field">
                        <label for="summary">Short summary</label>
                        <input type="text" id="summary" name="summary" maxlength="255"
                               value="<?= e($lesson['summary']) ?>">
                    </div>

                    <div class="field">
                        <label for="body">Lesson text</label>
                        <textarea id="body" name="body" rows="18" required><?= e($lesson['body']) ?></textarea>
                        <small class="hint">
                            Start a heading with <code>## </code>. Start bullet lines with <code>- </code>.
                            Leave a blank line between paragraphs. Line breaks inside a paragraph are kept.
                        </small>
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="published" value="1"<?= $lesson['published'] ? ' checked' : '' ?>>
                        <span>Published (visible to students)</span>
                    </label>
                </div>

                <div class="panel stack">
                    <h2>Quiz questions</h2>
                    <p class="muted">This is the question bank. Students get <?= QUIZ_SIZE ?> random questions from it on each attempt, so add at least <?= QUIZ_SIZE ?>. Use Add another question for more (up to 60). Leave a question empty to skip it, or clear its text to remove an existing one.</p>

                    <?php for ($i = 0; $i < $slots; $i++): ?>
                        <?php $q = $questions[$i] ?? blank_question(); ?>
                        <fieldset class="q-block">
                            <legend>Question <?= $i + 1 ?></legend>
                            <input type="hidden" name="q[<?= $i ?>][id]" value="<?= (int) $q['id'] ?>">

                            <div class="field">
                                <label for="q<?= $i ?>-question">Question</label>
                                <input type="text" id="q<?= $i ?>-question" name="q[<?= $i ?>][question]"
                                       maxlength="500" value="<?= e($q['question']) ?>">
                            </div>

                            <div class="field-row">
                                <?php foreach (['a' => 'option_a', 'b' => 'option_b', 'c' => 'option_c', 'd' => 'option_d'] as $key => $col): ?>
                                    <div class="field">
                                        <label for="q<?= $i ?>-<?= $key ?>">Option <?= strtoupper($key) ?></label>
                                        <input type="text" id="q<?= $i ?>-<?= $key ?>" name="q[<?= $i ?>][<?= $key ?>]"
                                               maxlength="255" value="<?= e($q[$col]) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="field-row">
                                <div class="field">
                                    <label for="q<?= $i ?>-correct">Correct answer</label>
                                    <select id="q<?= $i ?>-correct" name="q[<?= $i ?>][correct]">
                                        <?php foreach ($letters as $letter): ?>
                                            <option value="<?= $letter ?>"<?= $q['correct_option'] === $letter ? ' selected' : '' ?>><?= $letter ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="q<?= $i ?>-explanation">Explanation (shown after the quiz)</label>
                                    <input type="text" id="q<?= $i ?>-explanation" name="q[<?= $i ?>][explanation]"
                                           maxlength="500" value="<?= e($q['explanation']) ?>">
                                </div>
                            </div>
                        </fieldset>
                    <?php endfor; ?>
                    <div id="more-questions" class="stack"></div>
                    <button type="button" class="btn btn-secondary" id="add-question">Add another question</button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save lesson</button>
                    <a class="btn btn-secondary" href="admin-lessons.php">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</main>
<script>window.QUIZ = { next: <?= (int) $slots ?>, max: <?= MAX_QUESTIONS ?> };</script>
<script src="assets/js/lesson-editor.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
