<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/moderation.php';

require_login();
require_admin($conn);

$subjects = [];
foreach ($conn->query('SELECT slug, name FROM subjects ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC) as $row) {
    $subjects[$row['slug']] = $row['name'];
}
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $lessonId = (int) ($_POST['id'] ?? 0);

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($lessonId < 1) {
        $errors[] = 'That lesson could not be found.';
    } elseif ($action === 'toggle') {
        $stmt = $conn->prepare('UPDATE lessons SET published = 1 - published WHERE id = ?');
        $stmt->bind_param('i', $lessonId);
        $stmt->execute();

        $_SESSION['flash'] = 'Lesson visibility updated.';
        header('Location: admin-lessons.php');
        exit;
    } elseif ($action === 'delete') {
        $conn->begin_transaction();
        try {
            foreach (['lesson_completions' => 'lesson_id', 'quiz_questions' => 'lesson_id', 'lessons' => 'id'] as $table => $col) {
                $stmt = $conn->prepare("DELETE FROM $table WHERE $col = ?");
                $stmt->bind_param('i', $lessonId);
                $stmt->execute();
            }
            $conn->commit();
        } catch (Throwable $ex) {
            $conn->rollback();
            throw $ex;
        }

        $_SESSION['flash'] = 'Lesson deleted.';
        header('Location: admin-lessons.php');
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$rows = $conn->query(
    'SELECT l.id, l.subject_slug, l.slug, l.title, l.published,
            (SELECT COUNT(*) FROM quiz_questions q WHERE q.lesson_id = l.id) AS questions,
            (SELECT COUNT(*) FROM lesson_completions c WHERE c.lesson_id = l.id) AS completions
     FROM lessons l
     ORDER BY l.subject_slug, l.sort_order, l.id'
)->fetch_all(MYSQLI_ASSOC);

$grouped = [];
foreach ($rows as $row) {
    $grouped[$row['subject_slug']][] = $row;
}

$pageTitle = 'Manage lessons';
$active    = 'admin';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="admin.php">Admin</a></p>
            <h1>Lessons</h1>
            <p>Create and edit lessons and their quizzes.</p>
            <p><a class="btn btn-primary btn-sm" href="admin-lesson-edit.php">New lesson</a></p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow stack">

            <?php if ($flash !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($flash) ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-error" role="alert">
                    <ul>
                        <?php foreach ($errors as $msg): ?>
                            <li><?= e($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$grouped): ?>
                <div class="panel empty">
                    <h2>No lessons yet</h2>
                    <p>Create your first lesson to get started.</p>
                    <a class="btn btn-primary" href="admin-lesson-edit.php">New lesson</a>
                </div>
            <?php endif; ?>

            <?php foreach ($grouped as $subjectSlug => $lessons): ?>
                <div class="panel">
                    <h2><?= e($subjects[$subjectSlug] ?? $subjectSlug) ?></h2>
                    <div class="stack">
                        <?php foreach ($lessons as $l): ?>
                            <div class="admin-row">
                                <div class="admin-row-main">
                                    <strong><?= e($l['title']) ?></strong>
                                    <span class="badge <?= $l['published'] ? 'badge-live' : 'badge-draft' ?>">
                                        <?= $l['published'] ? 'Published' : 'Draft' ?>
                                    </span>
                                    <small class="hint"><?= (int) $l['questions'] ?> questions &middot; <?= (int) $l['completions'] ?> completed</small>
                                </div>
                                <div class="admin-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="admin-lesson-edit.php?id=<?= (int) $l['id'] ?>">Edit</a>
                                    <a class="btn btn-secondary btn-sm" target="_blank" rel="noopener"
                                       href="lesson.php?subject=<?= e(urlencode($l['subject_slug'])) ?>&amp;lesson=<?= e(urlencode($l['slug'])) ?>">Preview</a>
                                    <form method="post" action="admin-lessons.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm"><?= $l['published'] ? 'Unpublish' : 'Publish' ?></button>
                                    </form>
                                    <form method="post" action="admin-lessons.php"
                                          onsubmit="return confirm('Delete this lesson, its quiz and its completion records?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
