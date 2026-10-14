<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

require_login();

$userId = (int) $_SESSION['user_id'];

// Current user
$stmt = $conn->prepare('SELECT username, xp, level FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { // account was deleted while the session was alive
    header('Location: logout.php');
    exit;
}

// Rank = number of users with more XP, plus one
$stmt = $conn->prepare('SELECT COUNT(*) + 1 AS user_rank FROM users WHERE xp > ?');
$stmt->bind_param('i', $user['xp']);
$stmt->execute();
$rank = (int) $stmt->get_result()->fetch_assoc()['user_rank'];

// Top 5 leaderboard
$leaders = $conn->query(
    'SELECT id, username, xp FROM users ORDER BY xp DESC, id ASC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

// Latest XP activity for this user
$stmt = $conn->prepare(
    'SELECT xp_earned, reason, created_at FROM xp_logs
     WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$subjects = require __DIR__ . '/includes/subjects.php';
$medals   = ['🥇', '🥈', '🥉', '4', '5'];

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow">Dashboard</p>
            <h1>Welcome back, <?= e($user['username']) ?> 👋</h1>
            <p>Keep learning, join the conversation and climb the leaderboard.</p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container">

            <div class="stat-grid">
                <div class="stat-card">
                    <span class="stat-label">Total XP</span>
                    <span class="stat-value"><?= number_format((int) $user['xp']) ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Level</span>
                    <span class="stat-value"><?= (int) $user['level'] ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Your rank</span>
                    <span class="stat-value">#<?= $rank ?></span>
                </div>
            </div>

            <div class="panel-grid">

                <div class="panel">
                    <h2>Leaderboard</h2>
                    <ol class="rank-list">
                        <?php foreach ($leaders as $i => $row): ?>
                            <li class="<?= (int) $row['id'] === $userId ? 'is-you' : '' ?>">
                                <span class="rank-pos"><?= $medals[$i] ?></span>
                                <span class="rank-name">
                                    <?= e($row['username']) ?>
                                    <?= (int) $row['id'] === $userId ? '<em>(you)</em>' : '' ?>
                                </span>
                                <span class="rank-xp"><?= number_format((int) $row['xp']) ?> XP</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <div class="panel">
                    <h2>Recent activity</h2>
                    <?php if ($activity): ?>
                        <ul class="activity-list">
                            <?php foreach ($activity as $row): ?>
                                <li>
                                    <strong>+<?= (int) $row['xp_earned'] ?> XP</strong>
                                    <span><?= e($row['reason'] ?: 'XP earned') ?></span>
                                    <time><?= e(date('j M Y', strtotime($row['created_at']))) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">No XP activity yet. Complete a lesson or join a discussion to start earning.</p>
                    <?php endif; ?>
                </div>

                <div class="panel panel-wide">
                    <h2>Subjects</h2>
                    <div class="chip-list">
                        <?php foreach ($subjects as $slug => $name): ?>
                            <a class="chip" href="subjects/<?= e($slug) ?>.php"><?= e($name) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
