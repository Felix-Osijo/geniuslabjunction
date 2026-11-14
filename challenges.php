<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/challenge.php';

require_login();

$userId = (int) $_SESSION['user_id'];

expire_challenges($conn);

$stmt = $conn->prepare(
    'SELECT c.id, c.topic, c.question_count, c.time_limit_min, c.status, c.winner_id, c.challenger_id,
            TIMESTAMPDIFF(MINUTE, NOW(), c.expires_at) AS minutes_left,
            a.username AS challenger_name, b.username AS opponent_name,
            (SELECT t.submitted_at FROM challenge_attempts t WHERE t.challenge_id = c.id AND t.user_id = ?) AS my_done
     FROM challenges c
     JOIN users a ON a.id = c.challenger_id
     JOIN users b ON b.id = c.opponent_id
     WHERE c.challenger_id = ? OR c.opponent_id = ?
     ORDER BY c.id DESC
     LIMIT 60'
);
$stmt->bind_param('iii', $userId, $userId, $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$waitingMe   = [];
$waitingThem = [];
$finished    = [];

foreach ($rows as $r) {
    if ($r['status'] !== 'open') {
        $finished[] = $r;
    } elseif ($r['my_done'] === null) {
        $waitingMe[] = $r;
    } else {
        $waitingThem[] = $r;
    }
}

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(winner_id = ?), 0),
            COALESCE(SUM(status = 'done' AND winner_id IS NOT NULL AND winner_id <> ?), 0),
            COALESCE(SUM(status = 'done' AND winner_id IS NULL), 0)
     FROM challenges WHERE challenger_id = ? OR opponent_id = ?"
);
$stmt->bind_param('iiii', $userId, $userId, $userId, $userId);
$stmt->execute();
[$wins, $losses, $draws] = array_map('intval', $stmt->get_result()->fetch_row());

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function challenge_line(array $r, int $userId, string $label, string $button): void
{
    $oppName = (int) $r['challenger_id'] === $userId ? $r['opponent_name'] : $r['challenger_name'];
    $minutes = max(0, (int) $r['minutes_left']);
    $left    = $minutes >= 1440 ? intdiv($minutes, 1440) . 'd left' : ($minutes >= 60 ? intdiv($minutes, 60) . 'h left' : $minutes . 'm left');
    ?>
    <div class="admin-row">
        <div class="admin-row-main">
            <strong>vs <?= e($oppName) ?></strong>
            <small class="hint">
                <?= e($r['topic']) ?> &middot; <?= (int) $r['question_count'] ?> questions
                &middot; <?= (int) $r['time_limit_min'] > 0 ? (int) $r['time_limit_min'] . ' min' : 'no timer' ?>
                <?= $r['status'] === 'open' ? ' &middot; ' . e($left) : '' ?>
            </small>
        </div>
        <div class="admin-row-actions">
            <?php if ($label !== ''): ?><span class="badge badge-live"><?= e($label) ?></span><?php endif; ?>
            <a class="btn btn-secondary btn-sm" href="challenge.php?id=<?= (int) $r['id'] ?>"><?= e($button) ?></a>
        </div>
    </div>
    <?php
}

$pageTitle = 'Challenges';
$active    = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow">Quiz challenges</p>
            <h1>Challenges</h1>
            <p>Challenge a student to the same quiz and see who scores higher.</p>
            <p><a class="btn btn-primary btn-sm" href="challenge-new.php">New challenge</a></p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow stack">

            <?php if ($flash !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($flash) ?></div>
            <?php endif; ?>

            <div class="stat-grid">
                <div class="stat-card"><span class="stat-label">Wins</span><span class="stat-value"><?= $wins ?></span></div>
                <div class="stat-card"><span class="stat-label">Losses</span><span class="stat-value"><?= $losses ?></span></div>
                <div class="stat-card"><span class="stat-label">Draws</span><span class="stat-value"><?= $draws ?></span></div>
            </div>

            <div class="panel stack">
                <h2>Waiting for you (<?= count($waitingMe) ?>)</h2>
                <?php foreach ($waitingMe as $r) { challenge_line($r, $userId, '', 'Play'); } ?>
                <?php if (!$waitingMe): ?><p class="muted">Nothing waiting. Start a new challenge.</p><?php endif; ?>
            </div>

            <div class="panel stack">
                <h2>Waiting for your opponent (<?= count($waitingThem) ?>)</h2>
                <?php foreach ($waitingThem as $r) { challenge_line($r, $userId, '', 'View'); } ?>
                <?php if (!$waitingThem): ?><p class="muted">No open challenges here.</p><?php endif; ?>
            </div>

            <div class="panel stack">
                <h2>Finished</h2>
                <?php foreach ($finished as $r): ?>
                    <?php
                    if ($r['status'] === 'expired') {
                        $label = 'Expired';
                    } elseif ($r['winner_id'] === null) {
                        $label = 'Draw';
                    } else {
                        $label = (int) $r['winner_id'] === $userId ? 'Won' : 'Lost';
                    }
                    challenge_line($r, $userId, $label, 'Results');
                    ?>
                <?php endforeach; ?>
                <?php if (!$finished): ?><p class="muted">No finished challenges yet.</p><?php endif; ?>
            </div>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
