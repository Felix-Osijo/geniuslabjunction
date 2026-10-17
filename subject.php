<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/moderation.php';

$slug   = trim((string) ($_GET['s'] ?? ''));
$userId = (int) ($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare('SELECT slug, name, description, published FROM subjects WHERE slug = ?');
$stmt->bind_param('s', $slug);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

$isAdmin = $userId > 0 && user_role($conn, $userId) === 'admin';

if (!$subject || (!$subject['published'] && !$isAdmin)) {
    http_response_code(404);
    $pageTitle = 'Subject not found';
    require __DIR__ . '/includes/header.php';
    ?>
    <main>
        <section class="section">
            <div class="container narrow">
                <div class="panel empty">
                    <h2>Subject not found</h2>
                    <p>That subject does not exist or is not available yet.</p>
                    <a class="btn btn-primary" href="homepage.php#subjects">Browse subjects</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$adminFlag = $isAdmin ? 1 : 0;
$stmt = $conn->prepare(
    'SELECT l.slug, l.title, l.summary, l.published, (c.user_id IS NOT NULL) AS done
     FROM lessons l
     LEFT JOIN lesson_completions c ON c.lesson_id = l.id AND c.user_id = ?
     WHERE l.subject_slug = ? AND (l.published = 1 OR ? = 1)
     ORDER BY l.sort_order, l.id'
);
$stmt->bind_param('isi', $userId, $slug, $adminFlag);
$stmt->execute();
$lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$intro = $subject['description'] !== ''
    ? $subject['description']
    : ($lessons ? 'Read each lesson, then take the quiz to earn XP.' : 'Lessons for ' . $subject['name'] . ' are on the way.');

$pageTitle = $subject['name'];
$active    = 'home';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">Subject</p>
            <h1><?= e($subject['name']) ?></h1>
            <p><?= e($intro) ?></p>
        </div>
    </section>

    <section class="section">
        <div class="container narrow stack">

            <?php if (!$subject['published']): ?>
                <div class="alert alert-info" role="status">Hidden: only admins can see this subject.</div>
            <?php endif; ?>

            <?php if ($lessons): ?>
                <div class="lesson-list">
                    <?php foreach ($lessons as $i => $lesson): ?>
                        <a class="lesson-card"
                           href="lesson.php?subject=<?= e(urlencode($slug)) ?>&amp;lesson=<?= e(urlencode($lesson['slug'])) ?>">
                            <div>
                                <h3><?= $i + 1 ?>. <?= e($lesson['title']) ?></h3>
                                <p><?= e($lesson['summary']) ?></p>
                            </div>
                            <?php if (!$lesson['published']): ?>
                                <span class="badge badge-draft">Draft</span>
                            <?php elseif ($lesson['done']): ?>
                                <span class="tag">Completed</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="panel empty">
                    <h2>Coming soon</h2>
                    <p>We're still building this subject. In the meantime, explore the other subjects.</p>
                    <a class="btn btn-primary" href="homepage.php#subjects">Back to subjects</a>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
