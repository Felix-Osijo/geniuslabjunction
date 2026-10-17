<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

require_login();

$viewerId = (int) $_SESSION['user_id'];
$username = trim((string) ($_GET['u'] ?? ''));

function count_for(mysqli $conn, string $sql, int $value): int
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $value);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_row()[0];
}

$stmt = $conn->prepare('SELECT id, username, xp, level, created_at FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

if (!$profile) {
    http_response_code(404);
    $pageTitle = 'Profile not found';
    require __DIR__ . '/includes/header.php';
    ?>
    <main>
        <section class="section">
            <div class="container narrow">
                <div class="panel empty">
                    <h2>Profile not found</h2>
                    <p>We couldn't find a student with that username.</p>
                    <a class="btn btn-primary" href="feed.php">Back to the feed</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$profileId = (int) $profile['id'];
$isOwn     = $profileId === $viewerId;

$rank        = count_for($conn, 'SELECT COUNT(*) + 1 FROM users WHERE xp > ?', (int) $profile['xp']);
$postCount   = count_for($conn, 'SELECT COUNT(*) FROM posts WHERE user_id = ?', $profileId);
$likesGained = count_for($conn, 'SELECT COUNT(*) FROM likes l JOIN posts p ON p.id = l.post_id WHERE p.user_id = ?', $profileId);
$lessonsDone = count_for($conn, 'SELECT COUNT(*) FROM lesson_completions WHERE user_id = ?', $profileId);

$stmt = $conn->prepare(
    'SELECT p.id, p.content, p.created_at,
            (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS like_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
     FROM posts p
     WHERE p.user_id = ?
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 10'
);
$stmt->bind_param('i', $profileId);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = $profile['username'];
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container profile-head">
            <span class="avatar avatar-lg" aria-hidden="true"><?= e(strtoupper(mb_substr($profile['username'], 0, 1))) ?></span>
            <div>
                <p class="eyebrow"><?= $isOwn ? 'Your profile' : 'Student profile' ?></p>
                <h1><?= e($profile['username']) ?></h1>
                <p>Member since <?= e(date('F Y', strtotime($profile['created_at']))) ?></p>
                <?php if ($isOwn): ?>
                    <p><a class="btn btn-secondary btn-sm" href="account.php">Change password</a></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container">

            <div class="stat-grid">
                <div class="stat-card">
                    <span class="stat-label">Total XP</span>
                    <span class="stat-value"><?= number_format((int) $profile['xp']) ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Level</span>
                    <span class="stat-value"><?= (int) $profile['level'] ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Rank</span>
                    <span class="stat-value">#<?= $rank ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Lessons completed</span>
                    <span class="stat-value"><?= $lessonsDone ?></span>
                </div>
            </div>

            <div class="panel">
                <h2>Posts (<?= $postCount ?>) &middot; <?= $likesGained ?> <?= $likesGained === 1 ? 'like' : 'likes' ?> received</h2>

                <?php if ($posts): ?>
                    <div class="stack">
                        <?php foreach ($posts as $post): ?>
                            <article class="profile-post">
                                <p><?= nl2br(e($post['content'])) ?></p>
                                <p class="profile-post-meta">
                                    <?= e(date('j M Y, g:i A', strtotime($post['created_at']))) ?>
                                    &middot; &hearts; <?= (int) $post['like_count'] ?>
                                    &middot; <?= (int) $post['comment_count'] ?> <?= (int) $post['comment_count'] === 1 ? 'comment' : 'comments' ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted"><?= $isOwn ? 'You have not posted yet.' : 'No posts yet.' ?></p>
                <?php endif; ?>
            </div>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
