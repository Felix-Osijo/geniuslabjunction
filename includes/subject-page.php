<?php
require_once __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

$subjects = require __DIR__ . '/subjects.php';
$name     = $subjects[$slug] ?? 'Subject';
$userId   = (int) ($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT l.slug, l.title, l.summary, (c.user_id IS NOT NULL) AS done
     FROM lessons l
     LEFT JOIN lesson_completions c ON c.lesson_id = l.id AND c.user_id = ?
     WHERE l.subject_slug = ? AND l.published = 1
     ORDER BY l.sort_order, l.id'
);
$stmt->bind_param('is', $userId, $slug);
$stmt->execute();
$lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$base      = '../';
$pageTitle = $name;
$active    = 'home';

require __DIR__ . '/header.php';
?>
<main>
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">Subject</p>
            <h1><?= e($name) ?></h1>
            <p><?= $lessons ? 'Read each lesson, then take the quiz to earn XP.' : 'Lessons for ' . e($name) . ' are on the way.' ?></p>
        </div>
    </section>

    <section class="section">
        <div class="container narrow">
            <?php if ($lessons): ?>
                <div class="lesson-list">
                    <?php foreach ($lessons as $i => $lesson): ?>
                        <a class="lesson-card"
                           href="../lesson.php?subject=<?= e(urlencode($slug)) ?>&amp;lesson=<?= e(urlencode($lesson['slug'])) ?>">
                            <div>
                                <h3><?= $i + 1 ?>. <?= e($lesson['title']) ?></h3>
                                <p><?= e($lesson['summary']) ?></p>
                            </div>
                            <?php if ($lesson['done']): ?>
                                <span class="tag">Completed</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="panel empty">
                    <h2>Coming soon</h2>
                    <p>We're still building this subject. In the meantime, explore the other subjects.</p>
                    <a class="btn btn-primary" href="../homepage.php#subjects">Back to subjects</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/footer.php'; ?>
