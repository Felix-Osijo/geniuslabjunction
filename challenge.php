<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/challenge.php';

require_login();

$userId = (int) $_SESSION['user_id'];
$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

function challenge_missing(): void
{
    http_response_code(404);
    $pageTitle = 'Challenge not found';
    require __DIR__ . '/includes/header.php';
    ?>
    <main>
        <section class="section">
            <div class="container narrow">
                <div class="panel empty">
                    <h2>Challenge not found</h2>
                    <p>That challenge does not exist, or it is not yours.</p>
                    <a class="btn btn-primary" href="challenges.php">Back to challenges</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

function load_attempts(mysqli $conn, int $challengeId): array
{
    $stmt = $conn->prepare(
        'SELECT user_id, submitted_at, score, time_taken, answers,
                TIMESTAMPDIFF(SECOND, started_at, NOW()) AS elapsed
         FROM challenge_attempts WHERE challenge_id = ?'
    );
    $stmt->bind_param('i', $challengeId);
    $stmt->execute();

    $byUser = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $byUser[(int) $row['user_id']] = $row;
    }

    return $byUser;
}

expire_challenges($conn);

$stmt = $conn->prepare(
    'SELECT c.id, c.challenger_id, c.opponent_id, c.topic, c.question_ids, c.question_count,
            c.time_limit_min, c.status, c.winner_id, c.xp_awarded,
            TIMESTAMPDIFF(MINUTE, NOW(), c.expires_at) AS minutes_left,
            a.username AS challenger_name, b.username AS opponent_name
     FROM challenges c
     JOIN users a ON a.id = c.challenger_id
     JOIN users b ON b.id = c.opponent_id
     WHERE c.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

$isChallenger = $c && (int) $c['challenger_id'] === $userId;
$isOpponent   = $c && (int) $c['opponent_id'] === $userId;

if (!$c || (!$isChallenger && !$isOpponent)) {
    challenge_missing();
}

$oppId   = $isChallenger ? (int) $c['opponent_id'] : (int) $c['challenger_id'];
$oppName = $isChallenger ? $c['opponent_name'] : $c['challenger_name'];
$limit   = (int) $c['time_limit_min'] * 60;
$total   = (int) $c['question_count'];
$self    = 'challenge.php?id=' . $id;
$errors  = [];

$attempts = load_attempts($conn, $id);
$mine     = $attempts[$userId] ?? null;
$theirs   = $attempts[$oppId] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $c['status'] === 'open' && $mine && $mine['submitted_at'] === null
    && $limit > 0 && (int) $mine['elapsed'] > $limit + CHALLENGE_GRACE) {
    submit_attempt($conn, $c, $userId, [], (int) $mine['elapsed']);
    header('Location: ' . $self);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($c['status'] !== 'open') {
        $errors[] = 'This challenge has ended.';
    } elseif ($action === 'start') {
        if (!$mine) {
            $stmt = $conn->prepare(
                'INSERT IGNORE INTO challenge_attempts (challenge_id, user_id, started_at) VALUES (?, ?, NOW())'
            );
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
        }
        header('Location: ' . $self);
        exit;
    } elseif ($action === 'submit') {
        if (!$mine || $mine['submitted_at'] !== null) {
            $errors[] = 'There is nothing to submit.';
        } else {
            $answers = $_POST['answer'] ?? [];
            $answers = is_array($answers) ? $answers : [];
            submit_attempt($conn, $c, $userId, $answers, (int) $mine['elapsed']);
            header('Location: ' . $self);
            exit;
        }
    }
}

$iDone  = $mine && $mine['submitted_at'] !== null;
$status = $c['status'];

$questions = [];
if ($status === 'done' || ($status === 'open' && $mine && !$iDone)) {
    $questions = load_questions_by_ids($conn, array_map('intval', explode(',', $c['question_ids'])));
}

$myAnswers = $theirAnswers = [];
if ($status === 'done') {
    $myAnswers    = json_decode((string) ($mine['answers'] ?? ''), true) ?: [];
    $theirAnswers = json_decode((string) ($theirs['answers'] ?? ''), true) ?: [];
}

$minutes  = max(0, (int) $c['minutes_left']);
$timeLeft = $minutes >= 1440 ? intdiv($minutes, 1440) . ' day(s)' : ($minutes >= 60 ? intdiv($minutes, 60) . ' hour(s)' : $minutes . ' minute(s)');

$winnerId = $c['winner_id'] === null ? null : (int) $c['winner_id'];
if ($winnerId === null) {
    $headline = "It's a draw!";
} elseif ($winnerId === $userId) {
    $headline = 'You won!';
} else {
    $headline = $oppName . ' won this one.';
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$optionCols = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];

$pageTitle = 'Challenge';
$active    = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="challenges.php">Challenges</a></p>
            <h1>You vs <?= e($oppName) ?></h1>
            <p><?= e($c['topic']) ?> &middot; <?= $total ?> questions &middot; <?= $limit > 0 ? (int) $c['time_limit_min'] . ' min timer' : 'no timer' ?></p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow stack">

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

            <?php if ($status === 'done'): ?>

                <div class="panel stack">
                    <h2><?= e($headline) ?></h2>
                    <div class="scoreboard">
                        <div class="score-card<?= $winnerId === $userId ? ' is-winner' : '' ?>">
                            <span class="stat-label">You</span>
                            <span class="stat-value"><?= (int) $mine['score'] ?> / <?= $total ?></span>
                            <small class="hint">Time <?= e(format_seconds((int) $mine['time_taken'])) ?></small>
                        </div>
                        <div class="score-card<?= $winnerId === $oppId ? ' is-winner' : '' ?>">
                            <span class="stat-label"><?= e($oppName) ?></span>
                            <span class="stat-value"><?= (int) $theirs['score'] ?> / <?= $total ?></span>
                            <small class="hint">Time <?= e(format_seconds((int) $theirs['time_taken'])) ?></small>
                        </div>
                    </div>
                    <?php if ($winnerId === $userId): ?>
                        <p class="muted">
                            <?= $c['xp_awarded']
                                ? '+' . XP_CHALLENGE . ' XP for the win.'
                                : 'No XP this time: you reached your daily limit, or you already earned XP from a win against this student today.' ?>
                        </p>
                    <?php endif; ?>
                    <p><a class="btn btn-primary" href="challenge-new.php?to=<?= e(urlencode($oppName)) ?>">Challenge again</a></p>
                </div>

                <div class="panel quiz">
                    <h2>Review</h2>
                    <?php foreach ($questions as $i => $q): ?>
                        <?php
                        $mineLetter   = $myAnswers[$q['id']] ?? '';
                        $theirsLetter = $theirAnswers[$q['id']] ?? '';
                        ?>
                        <div class="quiz-q">
                            <p class="quiz-title"><?= $i + 1 ?>. <?= e($q['question']) ?></p>
                            <?php foreach ($optionCols as $letter => $col): ?>
                                <?php
                                $cls = '';
                                if ($letter === $q['correct_option']) {
                                    $cls = ' correct';
                                } elseif ($letter === $mineLetter) {
                                    $cls = ' wrong';
                                }
                                ?>
                                <div class="quiz-option<?= $cls ?>">
                                    <span><strong><?= $letter ?>.</strong> <?= e($q[$col]) ?></span>
                                    <?php if ($letter === $mineLetter): ?><span class="pick">You</span><?php endif; ?>
                                    <?php if ($letter === $theirsLetter): ?><span class="pick"><?= e($oppName) ?></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($q['explanation'] !== ''): ?>
                                <p class="explain"><?= e($q['explanation']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($status === 'expired'): ?>

                <div class="panel empty">
                    <h2>This challenge expired</h2>
                    <p>
                        <?= $iDone
                            ? 'You scored ' . (int) $mine['score'] . ' / ' . $total . ', but ' . e($oppName) . ' did not finish in time. No winner was recorded.'
                            : 'It was not finished within ' . CHALLENGE_DAYS . ' days, so no winner was recorded.' ?>
                    </p>
                    <a class="btn btn-primary" href="challenge-new.php?to=<?= e(urlencode($oppName)) ?>">Start a new one</a>
                </div>

            <?php elseif (!$mine): ?>

                <div class="panel stack">
                    <h2>Ready?</h2>
                    <p class="muted">
                        <?= $total ?> questions<?= $limit > 0 ? ' in ' . (int) $c['time_limit_min'] . ' minutes' : ', no timer' ?>.
                        <?= $limit > 0 ? 'The clock starts the moment you click Start and keeps running if you leave the page.' : '' ?>
                        You have <?= e($timeLeft) ?> left to play.
                    </p>
                    <form method="post" action="<?= e($self) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="start">
                        <button type="submit" class="btn btn-primary btn-lg">Start quiz</button>
                    </form>
                </div>

            <?php elseif (!$iDone): ?>

                <form method="post" action="<?= e($self) ?>" id="challenge-form" class="panel quiz">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="submit">

                    <?php if ($limit > 0): ?>
                        <div class="quiz-timer" id="quiz-timer" data-remaining="<?= max(0, $limit - (int) $mine['elapsed']) ?>">--:--</div>
                    <?php endif; ?>

                    <?php foreach ($questions as $i => $q): ?>
                        <fieldset class="quiz-q">
                            <legend><?= $i + 1 ?>. <?= e($q['question']) ?></legend>
                            <?php foreach ($optionCols as $letter => $col): ?>
                                <label class="quiz-option">
                                    <input type="radio" name="answer[<?= (int) $q['id'] ?>]" value="<?= $letter ?>">
                                    <span><strong><?= $letter ?>.</strong> <?= e($q[$col]) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary">Submit answers</button>
                </form>

            <?php else: ?>

                <div class="panel empty">
                    <h2>You scored <?= (int) $mine['score'] ?> / <?= $total ?></h2>
                    <p>
                        <?= $theirs && $theirs['submitted_at'] !== null
                            ? 'Calculating the result...'
                            : 'Waiting for ' . e($oppName) . ' to play. They have ' . e($timeLeft) . ' left. Answers and the winner are shown once you have both finished.' ?>
                    </p>
                    <a class="btn btn-secondary" href="challenges.php">Back to challenges</a>
                </div>

            <?php endif; ?>

        </div>
    </section>
</main>
<script src="assets/js/challenge.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
