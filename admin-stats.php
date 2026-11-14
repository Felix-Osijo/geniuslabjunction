<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/moderation.php';

require_login();
require_admin($conn);

function count_stat(mysqli $conn, string $sql): int
{
    return (int) $conn->query($sql)->fetch_row()[0];
}

$totals = [
    'Total accounts'    => count_stat($conn, 'SELECT COUNT(*) FROM users'),
    'New this week'     => count_stat($conn, 'SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY'),
    'Active this week'  => count_stat($conn,
        'SELECT COUNT(DISTINCT user_id) FROM (
            SELECT user_id FROM posts WHERE created_at >= NOW() - INTERVAL 7 DAY
            UNION SELECT user_id FROM comments WHERE created_at >= NOW() - INTERVAL 7 DAY
            UNION SELECT user_id FROM xp_logs WHERE created_at >= NOW() - INTERVAL 7 DAY
            UNION SELECT user_id FROM lesson_completions WHERE completed_at >= NOW() - INTERVAL 7 DAY
         ) a'),
    'Posts'             => count_stat($conn, 'SELECT COUNT(*) FROM posts'),
    'Comments'          => count_stat($conn, 'SELECT COUNT(*) FROM comments'),
    'Likes'             => count_stat($conn, 'SELECT COUNT(*) FROM likes'),
    'Lessons completed' => count_stat($conn, 'SELECT COUNT(*) FROM lesson_completions'),
    'Open reports'      => count_stat($conn, "SELECT COUNT(*) FROM reports WHERE status = 'open'"),
];

$weeks = $conn->query(
    'SELECT DATE(created_at - INTERVAL WEEKDAY(created_at) DAY) AS week_start, COUNT(*) AS signups
     FROM users
     WHERE created_at >= NOW() - INTERVAL 8 WEEK
     GROUP BY week_start
     ORDER BY week_start DESC'
)->fetch_all(MYSQLI_ASSOC);

$maxSignups = 1;
foreach ($weeks as $w) {
    $maxSignups = max($maxSignups, (int) $w['signups']);
}

$names = [];
foreach ($conn->query('SELECT slug, name FROM subjects')->fetch_all(MYSQLI_ASSOC) as $row) {
    $names[$row['slug']] = $row['name'];
}

$lessons = $conn->query(
    'SELECT l.id, l.title, l.subject_slug, l.published,
            (SELECT COUNT(*) FROM quiz_questions q WHERE q.lesson_id = l.id) AS bank,
            COUNT(c.user_id) AS completions,
            COALESCE(ROUND(AVG(c.best_score)), 0) AS avg_score
     FROM lessons l
     LEFT JOIN lesson_completions c ON c.lesson_id = l.id
     GROUP BY l.id, l.title, l.subject_slug, l.published
     ORDER BY completions DESC, l.title'
)->fetch_all(MYSQLI_ASSOC);

$topEarners = $conn->query(
    'SELECT u.username, SUM(x.xp_earned) AS xp
     FROM xp_logs x
     JOIN users u ON u.id = x.user_id
     WHERE x.created_at >= NOW() - INTERVAL 7 DAY
     GROUP BY u.id, u.username
     ORDER BY xp DESC
     LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Stats';
$active    = 'admin';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="admin.php">Admin</a></p>
            <h1>Stats</h1>
            <p>How students are using Genius Hub.</p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container stack">

            <div class="stat-grid">
                <?php foreach ($totals as $label => $value): ?>
                    <div class="stat-card">
                        <span class="stat-label"><?= e($label) ?></span>
                        <span class="stat-value"><?= number_format($value) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="panel">
                <h2>Signups per week (last 8 weeks)</h2>
                <?php if ($weeks): ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr><th>Week starting</th><th class="num">Signups</th><th class="bar-cell"></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weeks as $w): ?>
                                    <tr>
                                        <td><?= e(date('j M Y', strtotime($w['week_start']))) ?></td>
                                        <td class="num"><?= (int) $w['signups'] ?></td>
                                        <td class="bar-cell"><span class="bar" style="width: <?= (int) round($w['signups'] / $maxSignups * 100) ?>%"></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">No signups in the last 8 weeks.</p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Lessons</h2>
                <?php if ($lessons): ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Lesson</th>
                                    <th>Subject</th>
                                    <th class="num">Questions</th>
                                    <th class="num">Completed</th>
                                    <th class="num">Avg score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lessons as $l): ?>
                                    <tr>
                                        <td>
                                            <?= e($l['title']) ?>
                                            <?php if (!$l['published']): ?><span class="badge badge-draft">Draft</span><?php endif; ?>
                                        </td>
                                        <td><?= e($names[$l['subject_slug']] ?? $l['subject_slug']) ?></td>
                                        <td class="num"><?= (int) $l['bank'] ?></td>
                                        <td class="num"><?= (int) $l['completions'] ?></td>
                                        <td class="num"><?= (int) $l['completions'] > 0 ? (int) $l['avg_score'] . '%' : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">No lessons yet.</p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Top XP earners this week</h2>
                <?php if ($topEarners): ?>
                    <ol class="rank-list">
                        <?php foreach ($topEarners as $i => $t): ?>
                            <li>
                                <span class="rank-pos"><?= $i + 1 ?></span>
                                <span class="rank-name"><a href="profile.php?u=<?= e(urlencode($t['username'])) ?>"><?= e($t['username']) ?></a></span>
                                <span class="rank-xp"><?= number_format((int) $t['xp']) ?> XP</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="muted">No XP earned this week yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
