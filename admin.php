<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/moderation.php';

require_login();
require_admin($conn);

$notice = '';
$errors = [];
$temp   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($action === 'delete_target' || $action === 'dismiss') {
        $reportId = (int) ($_POST['report_id'] ?? 0);

        $stmt = $conn->prepare('SELECT target_type, target_id FROM reports WHERE id = ?');
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();

        if (!$report) {
            $errors[] = 'That report no longer exists.';
        } elseif ($action === 'dismiss') {
            $stmt = $conn->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
            $stmt->bind_param('i', $reportId);
            $stmt->execute();
            $notice = 'Report dismissed.';
        } else {
            $targetId = (int) $report['target_id'];
            $report['target_type'] === 'post'
                ? delete_post($conn, $targetId)
                : delete_comment($conn, $targetId);
            $notice = 'Content deleted.';
        }
    } elseif ($action === 'reset_password') {
        $username = trim($_POST['username'] ?? '');

        $stmt = $conn->prepare('SELECT id, username FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();

        if (!$target) {
            $errors[] = 'No user with that username.';
        } else {
            $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $password = '';
            for ($i = 0; $i < 10; $i++) {
                $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $hash     = password_hash($password, PASSWORD_DEFAULT);
            $targetId = (int) $target['id'];

            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $targetId);
            $stmt->execute();

            $key  = strtolower($target['username']);
            $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
            $stmt->bind_param('s', $key);
            $stmt->execute();

            $temp = ['username' => $target['username'], 'password' => $password];
        }
    }
}

$reports = $conn->query(
    "SELECT r.id, r.target_type, r.reason, r.created_at,
            ru.username AS reporter,
            COALESCE(p.content, c.comment) AS body,
            COALESCE(pu.username, cu.username) AS author
     FROM reports r
     JOIN users ru ON ru.id = r.reporter_id
     LEFT JOIN posts p ON r.target_type = 'post' AND p.id = r.target_id
     LEFT JOIN comments c ON r.target_type = 'comment' AND c.id = r.target_id
     LEFT JOIN users pu ON pu.id = p.user_id
     LEFT JOIN users cu ON cu.id = c.user_id
     WHERE r.status = 'open'
     ORDER BY r.created_at DESC
     LIMIT 50"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Admin';
$active    = 'admin';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow">Admin</p>
            <h1>Moderation</h1>
            <p>Review reports and help students who are locked out.</p>
            <p class="hero-actions"><a class="btn btn-primary btn-sm" href="admin-lessons.php">Manage lessons</a> <a class="btn btn-secondary btn-sm" href="admin-subjects.php">Manage subjects</a></p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow stack">

            <?php if ($notice !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($notice) ?></div>
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

            <?php if ($temp): ?>
                <div class="alert alert-success" role="status">
                    Temporary password for <strong><?= e($temp['username']) ?></strong>:
                    <code class="temp-password"><?= e($temp['password']) ?></code>
                    Share it privately and ask them to change it on the Account page. It is shown only once.
                </div>
            <?php endif; ?>

            <div class="panel">
                <h2>Open reports (<?= count($reports) ?>)</h2>

                <?php if ($reports): ?>
                    <div class="stack">
                        <?php foreach ($reports as $r): ?>
                            <div class="report-item">
                                <p class="report-meta">
                                    <strong><?= e(REPORT_REASONS[$r['reason']] ?? $r['reason']) ?></strong>
                                    on a <?= e($r['target_type']) ?> by <strong><?= e($r['author'] ?? 'unknown') ?></strong>
                                    &middot; reported by <?= e($r['reporter']) ?>
                                    &middot; <?= e(date('j M Y, g:i A', strtotime($r['created_at']))) ?>
                                </p>
                                <blockquote><?= $r['body'] !== null
                                    ? nl2br(e(mb_strimwidth($r['body'], 0, 300, '...')))
                                    : '(content unavailable)' ?></blockquote>
                                <div class="report-actions">
                                    <form method="post" action="admin.php"
                                          onsubmit="return confirm('Delete this content?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_target">
                                        <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete content</button>
                                    </form>
                                    <form method="post" action="admin.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="dismiss">
                                        <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">Dismiss</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No open reports. Nice and quiet.</p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Reset a student's password</h2>
                <form class="form" method="post" action="admin.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <div class="field">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Generate temporary password</button>
                </form>
            </div>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
