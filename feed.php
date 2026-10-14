<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/xp.php';

require_login();

$userId  = (int) $_SESSION['user_id'];
$errors  = [];
$content = '';

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
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$posts = $conn->query(
    'SELECT p.id, p.content, p.created_at, u.username
     FROM posts p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 20'
)->fetch_all(MYSQLI_ASSOC);

$comments = [];
if ($posts) {
    $ids  = implode(',', array_map(fn($p) => (int) $p['id'], $posts));
    $rows = $conn->query(
        "SELECT c.post_id, c.comment, c.created_at, u.username
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

                            <p class="post-stats"><?= $count ?> <?= $count === 1 ? 'comment' : 'comments' ?></p>

                            <div class="comments">
                                <?php foreach ($postComments as $c): ?>
                                    <div class="comment">
                                        <span class="avatar avatar-sm" aria-hidden="true"><?= e(strtoupper(mb_substr($c['username'], 0, 1))) ?></span>
                                        <div class="comment-body">
                                            <strong><?= e($c['username']) ?></strong>
                                            <time datetime="<?= e($c['created_at']) ?>"><?= e(date('j M, g:i A', strtotime($c['created_at']))) ?></time>
                                            <p><?= nl2br(e($c['comment'])) ?></p>
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
