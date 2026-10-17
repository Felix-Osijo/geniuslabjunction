<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/xp.php';
require __DIR__ . '/includes/moderation.php';

require_login();

$userId  = (int) $_SESSION['user_id'];
$isAdmin = user_role($conn, $userId) === 'admin';
$errors  = [];
$content = '';

function item_actions(string $type, int $id, bool $canDelete, bool $canReport): void
{
    if (!$canDelete && !$canReport) {
        return;
    }
    ?>
    <div class="item-actions">
        <?php if ($canDelete): ?>
            <form method="post" action="feed.php" onsubmit="return confirm('Delete this <?= $type ?>?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_<?= $type ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="link-btn danger">Delete</button>
            </form>
        <?php endif; ?>
        <?php if ($canReport): ?>
            <details class="report">
                <summary>Report</summary>
                <form method="post" action="feed.php" class="report-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="report">
                    <input type="hidden" name="type" value="<?= $type ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <select name="reason" required aria-label="Reason">
                        <?php foreach (REPORT_REASONS as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="link-btn">Send</button>
                </form>
            </details>
        <?php endif; ?>
    </div>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($action === 'post') {
        $content = trim($_POST['content'] ?? '');
        $length  = mb_strlen($content);

        if ($length < 1) {
            $errors[] = 'Write something before posting.';
        } elseif ($length > 1000) {
            $errors[] = 'Posts can be up to 1000 characters.';
        } else {
            $stmt = $conn->prepare('INSERT INTO posts (user_id, content) VALUES (?, ?)');
            $stmt->bind_param('is', $userId, $content);
            $stmt->execute();

            $earned = award_xp($conn, $userId, XP_POST, 'Posted in the feed');
            if ($earned > 0) {
                $_SESSION['flash'] = "+$earned XP for posting";
            }

            header('Location: feed.php');
            exit;
        }
    } elseif ($action === 'comment') {
        $postId  = (int) ($_POST['post_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $length  = mb_strlen($comment);

        if ($postId < 1) {
            $errors[] = 'That post could not be found.';
        } elseif ($length < 1) {
            $errors[] = 'Write something before commenting.';
        } elseif ($length > 500) {
            $errors[] = 'Comments can be up to 500 characters.';
        } else {
            try {
                $stmt = $conn->prepare('INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)');
                $stmt->bind_param('iis', $postId, $userId, $comment);
                $stmt->execute();

                $earned = award_xp($conn, $userId, XP_COMMENT, 'Commented on a post');
                if ($earned > 0) {
                    $_SESSION['flash'] = "+$earned XP for commenting";
                }

                header('Location: feed.php#post-' . $postId);
                exit;
            } catch (mysqli_sql_exception $ex) {
                if ((int) $ex->getCode() === 1452) {
                    $errors[] = 'That post no longer exists.';
                } else {
                    throw $ex;
                }
            }
        }
    } elseif ($action === 'delete_post' || $action === 'delete_comment') {
        $type  = $action === 'delete_post' ? 'post' : 'comment';
        $id    = (int) ($_POST['id'] ?? 0);
        $owner = owner_of($conn, $type, $id);

        if ($owner === null) {
            $errors[] = 'That item no longer exists.';
        } elseif ($owner !== $userId && !$isAdmin) {
            $errors[] = 'You can only delete your own posts and comments.';
        } else {
            $type === 'post' ? delete_post($conn, $id) : delete_comment($conn, $id);
            $_SESSION['flash'] = ucfirst($type) . ' deleted.';
            header('Location: feed.php');
            exit;
        }
    } elseif ($action === 'report') {
        $type   = $_POST['type'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);
        $reason = $_POST['reason'] ?? '';

        if (!in_array($type, ['post', 'comment'], true) || $id < 1 || !isset(REPORT_REASONS[$reason])) {
            $errors[] = 'That report could not be sent.';
        } else {
            $owner = owner_of($conn, $type, $id);

            if ($owner === null) {
                $errors[] = 'That item no longer exists.';
            } elseif ($owner === $userId) {
                $errors[] = 'You cannot report your own content.';
            } else {
                $stmt = $conn->prepare(
                    'INSERT IGNORE INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)'
                );
                $stmt->bind_param('isis', $userId, $type, $id, $reason);
                $stmt->execute();

                $_SESSION['flash'] = $stmt->affected_rows > 0
                    ? 'Thanks. We will review your report.'
                    : 'You already reported this.';
                header('Location: feed.php');
                exit;
            }
        }
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$posts = $conn->query(
    'SELECT p.id, p.user_id, p.content, p.created_at, u.username
     FROM posts p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 20'
)->fetch_all(MYSQLI_ASSOC);

$comments = [];
if ($posts) {
    $ids  = implode(',', array_map(fn($p) => (int) $p['id'], $posts));
    $rows = $conn->query(
        "SELECT c.id, c.post_id, c.user_id, c.comment, c.created_at, u.username
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id IN ($ids)
         ORDER BY c.created_at ASC, c.id ASC"
    )->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $row) {
        $comments[$row['post_id']][] = $row;
    }
}

$pageTitle = 'Feed';
$active    = 'feed';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow">Community</p>
            <h1>Feed</h1>
            <p>Share what you're learning and see what other students are posting.</p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow">

            <?php if ($flash !== '' || $errors): ?>
                <div class="feed-alerts">
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
                </div>
            <?php endif; ?>

            <form class="panel composer" method="post" action="feed.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="post">

                <label class="sr-only" for="content">Write a post</label>
                <textarea id="content" name="content" maxlength="1000"
                          placeholder="What are you learning today?" required><?= e($content) ?></textarea>

                <div class="composer-actions">
                    <small class="hint">Up to 1000 characters</small>
                    <button type="submit" class="btn btn-primary">Post</button>
                </div>
            </form>

            <?php if ($posts): ?>
                <div class="feed">
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $postId       = (int) $post['id'];
                        $postComments = $comments[$postId] ?? [];
                        $count        = count($postComments);
                        $ownPost      = (int) $post['user_id'] === $userId;
                        ?>
                        <article class="panel post" id="post-<?= $postId ?>">
                            <header class="post-head">
                                <span class="avatar" aria-hidden="true"><?= e(strtoupper(mb_substr($post['username'], 0, 1))) ?></span>
                                <div class="post-meta">
                                    <strong><?= e($post['username']) ?></strong>
                                    <time datetime="<?= e($post['created_at']) ?>"><?= e(date('j M Y, g:i A', strtotime($post['created_at']))) ?></time>
                                </div>
                            </header>

                            <p class="post-body"><?= nl2br(e($post['content'])) ?></p>

                            <div class="post-foot">
                                <p class="post-stats"><?= $count ?> <?= $count === 1 ? 'comment' : 'comments' ?></p>
                                <?php item_actions('post', $postId, $ownPost || $isAdmin, !$ownPost && !$isAdmin); ?>
                            </div>

                            <div class="comments">
                                <?php foreach ($postComments as $c): ?>
                                    <?php $ownComment = (int) $c['user_id'] === $userId; ?>
                                    <div class="comment">
                                        <span class="avatar avatar-sm" aria-hidden="true"><?= e(strtoupper(mb_substr($c['username'], 0, 1))) ?></span>
                                        <div class="comment-body">
                                            <strong><?= e($c['username']) ?></strong>
                                            <time datetime="<?= e($c['created_at']) ?>"><?= e(date('j M, g:i A', strtotime($c['created_at']))) ?></time>
                                            <p><?= nl2br(e($c['comment'])) ?></p>
                                            <?php item_actions('comment', (int) $c['id'], $ownComment || $isAdmin, !$ownComment && !$isAdmin); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <form class="comment-form" method="post" action="feed.php#post-<?= $postId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="comment">
                                    <input type="hidden" name="post_id" value="<?= $postId ?>">
                                    <label class="sr-only" for="comment-<?= $postId ?>">Add a comment</label>
                                    <input type="text" id="comment-<?= $postId ?>" name="comment"
                                           maxlength="500" placeholder="Write a comment..." required>
                                    <button type="submit" class="btn btn-secondary btn-sm">Comment</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="panel empty">
                    <h2>No posts yet</h2>
                    <p>Be the first to share something with the community.</p>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
