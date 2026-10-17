<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/moderation.php';

require_login();
require_admin($conn);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $slug   = (string) ($_POST['slug'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($slug === '') {
        $errors[] = 'That subject could not be found.';
    } elseif ($action === 'toggle') {
        $stmt = $conn->prepare('UPDATE subjects SET published = 1 - published WHERE slug = ?');
        $stmt->bind_param('s', $slug);
        $stmt->execute();

        $_SESSION['flash'] = 'Subject visibility updated.';
        header('Location: admin-subjects.php');
        exit;
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM lessons WHERE subject_slug = ?');
        $stmt->bind_param('s', $slug);
        $stmt->execute();

        if ((int) $stmt->get_result()->fetch_row()[0] > 0) {
            $errors[] = 'This subject still has lessons. Delete or move them first, or hide the subject instead.';
        } else {
            $stmt = $conn->prepare('DELETE FROM subjects WHERE slug = ?');
            $stmt->bind_param('s', $slug);
            $stmt->execute();

            $_SESSION['flash'] = 'Subject deleted.';
            header('Location: admin-subjects.php');
            exit;
        }
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$rows = $conn->query(
    'SELECT s.slug, s.name, s.published,
            (SELECT COUNT(*) FROM lessons l WHERE l.subject_slug = s.slug) AS lesson_count
     FROM subjects s
     ORDER BY s.sort_order, s.name'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage subjects';
$active    = 'admin';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="admin.php">Admin</a></p>
            <h1>Subjects</h1>
            <p>Add and organise the subjects students can learn.</p>
            <p><a class="btn btn-primary btn-sm" href="admin-subject-edit.php">New subject</a></p>
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

            <div class="panel stack">
                <?php if (!$rows): ?>
                    <p class="muted">No subjects yet. Create the first one.</p>
                <?php endif; ?>

                <?php foreach ($rows as $r): ?>
                    <div class="admin-row">
                        <div class="admin-row-main">
                            <strong><?= e($r['name']) ?></strong>
                            <span class="badge <?= $r['published'] ? 'badge-live' : 'badge-draft' ?>">
                                <?= $r['published'] ? 'Visible' : 'Hidden' ?>
                            </span>
                            <small class="hint"><?= (int) $r['lesson_count'] ?> lessons</small>
                        </div>
                        <div class="admin-row-actions">
                            <a class="btn btn-secondary btn-sm" href="admin-subject-edit.php?slug=<?= e(urlencode($r['slug'])) ?>">Edit</a>
                            <a class="btn btn-secondary btn-sm" href="admin-lesson-edit.php?subject=<?= e(urlencode($r['slug'])) ?>">Add lesson</a>
                            <a class="btn btn-secondary btn-sm" target="_blank" rel="noopener" href="subject.php?s=<?= e(urlencode($r['slug'])) ?>">View</a>
                            <form method="post" action="admin-subjects.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="slug" value="<?= e($r['slug']) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"><?= $r['published'] ? 'Hide' : 'Show' ?></button>
                            </form>
                            <form method="post" action="admin-subjects.php"
                                  onsubmit="return confirm('Delete this subject?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="slug" value="<?= e($r['slug']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
