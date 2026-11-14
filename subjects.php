<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

require_login();

$userId   = (int) $_SESSION['user_id'];
$subjects = require __DIR__ . '/includes/subjects.php';

$stmt = $conn->prepare(
    'SELECT l.subject_slug, COUNT(*) AS lessons, COUNT(c.user_id) AS done
     FROM lessons l
     LEFT JOIN lesson_completions c ON c.lesson_id = l.id AND c.user_id = ?
     WHERE l.published = 1
     GROUP BY l.subject_slug'
);
$stmt->bind_param('i', $userId);
$stmt->execute();

$counts = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $counts[$row['subject_slug']] = $row;
}

$pageTitle = 'Subjects';
$active    = 'subjects';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow">Learn</p>
            <h1>Subjects</h1>
            <p>Pick a subject, read the lessons and take the quizzes to earn XP.</p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container">
            <?php if ($subjects): ?>
                <div class="subject-tiles">
                    <?php foreach ($subjects as $slug => $name): ?>
                        <?php
                        $lessons = (int) ($counts[$slug]['lessons'] ?? 0);
                        $done    = (int) ($counts[$slug]['done'] ?? 0);
                        $percent = $lessons > 0 ? (int) round($done / $lessons * 100) : 0;
                        ?>
                        <a class="subject-tile" href="subject.php?s=<?= e(urlencode($slug)) ?>">
                            <h3><?= e($name) ?></h3>
                            <small class="hint">
                                <?= $lessons === 0 ? 'Coming soon' : $lessons . ' lesson' . ($lessons === 1 ? '' : 's') . ' &middot; ' . $done . ' completed' ?>
                            </small>
                            <div class="progress" aria-hidden="true"><span style="width: <?= $percent ?>%"></span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="panel empty">
                    <h2>No subjects yet</h2>
                    <p>Subjects will appear here once they are added.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
