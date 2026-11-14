<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/challenge.php';

require_login();

$userId   = (int) $_SESSION['user_id'];
$subjects = require __DIR__ . '/includes/subjects.php';
$times    = challenge_time_options();
$errors   = [];

$old = [
    'opponent' => trim((string) ($_GET['to'] ?? '')),
    'subjects' => [],
    'lesson'   => 0,
    'count'    => 10,
    'time'     => 10,
];

$lessonMap = [];
$grouped   = [];
$rows = $conn->query(
    'SELECT l.id, l.title, l.subject_slug FROM lessons l
     WHERE l.published = 1 AND EXISTS (SELECT 1 FROM quiz_questions q WHERE q.lesson_id = l.id)
     ORDER BY l.subject_slug, l.sort_order, l.id'
)->fetch_all(MYSQLI_ASSOC);

foreach ($rows as $row) {
    if (isset($subjects[$row['subject_slug']])) {
        $lessonMap[(int) $row['id']]     = $row;
        $grouped[$row['subject_slug']][] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $picked = $_POST['subjects'] ?? [];
    $picked = is_array($picked) ? array_filter($picked, 'is_string') : [];

    $old['opponent'] = trim((string) ($_POST['opponent'] ?? ''));
    $old['subjects'] = array_values(array_unique(array_filter($picked, fn($slug) => isset($subjects[$slug]))));
    $old['lesson']   = (int) ($_POST['lesson'] ?? 0);
    $old['count']    = (int) ($_POST['count'] ?? 0);
    $old['time']     = (int) ($_POST['time'] ?? -1);

    $opponent = null;

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $stmt = $conn->prepare('SELECT id, username FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $old['opponent']);
        $stmt->execute();
        $opponent = $stmt->get_result()->fetch_assoc();

        if (!$opponent) {
            $errors[] = 'No student found with that username.';
        } elseif ((int) $opponent['id'] === $userId) {
            $errors[] = 'You cannot challenge yourself.';
        }

        if ($old['count'] < CHALLENGE_MIN_Q || $old['count'] > CHALLENGE_MAX_Q) {
            $errors[] = 'Choose between ' . CHALLENGE_MIN_Q . ' and ' . CHALLENGE_MAX_Q . ' questions.';
        }
        if (!isset($times[$old['time']])) {
            $errors[] = 'Choose a time limit.';
        }
        if ($old['lesson'] > 0) {
            if (!isset($lessonMap[$old['lesson']])) {
                $errors[] = 'Choose a lesson from the list.';
            }
        } elseif (!$old['subjects']) {
            $errors[] = 'Tick at least one subject, or choose a single lesson.';
        }
    }

    if (!$errors && $opponent) {
        $oppId = (int) $opponent['id'];

        $stmt = $conn->prepare('SELECT COUNT(*) FROM challenges WHERE challenger_id = ? AND created_at > NOW() - INTERVAL 1 DAY');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        if ((int) $stmt->get_result()->fetch_row()[0] >= CHALLENGE_DAILY_LIMIT) {
            $errors[] = 'You can send up to ' . CHALLENGE_DAILY_LIMIT . ' challenges a day. Try again tomorrow.';
        }

        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM challenges WHERE status = 'open'
             AND ((challenger_id = ? AND opponent_id = ?) OR (challenger_id = ? AND opponent_id = ?))"
        );
        $stmt->bind_param('iiii', $userId, $oppId, $oppId, $userId);
        $stmt->execute();
        if ((int) $stmt->get_result()->fetch_row()[0] >= CHALLENGE_OPEN_LIMIT) {
            $errors[] = 'You already have ' . CHALLENGE_OPEN_LIMIT . ' open challenges with this student. Finish one first.';
        }
    }

    if (!$errors && $opponent) {
        $ids = pick_challenge_questions($conn, $old['subjects'], $old['lesson'], $old['count']);

        if (count($ids) < CHALLENGE_MIN_Q) {
            $errors[] = 'There are not enough questions for that choice yet. Pick more subjects or a different lesson.';
        } elseif (count($ids) < $old['count']) {
            $errors[] = 'Only ' . count($ids) . ' questions are available for that choice. Lower the number or pick more subjects.';
        }
    }

    if (!$errors && $opponent) {
        if ($old['lesson'] > 0) {
            $row   = $lessonMap[$old['lesson']];
            $topic = $subjects[$row['subject_slug']] . ': ' . $row['title'];
        } else {
            $names = array_map(fn($slug) => $subjects[$slug], $old['subjects']);
            $topic = implode(', ', $names) . (count($names) > 1 ? ' (mixed)' : '');
        }
        $topic    = mb_substr($topic, 0, 255);
        $idList   = implode(',', $ids);
        $count    = count($ids);
        $timeMin  = $old['time'];
        $oppId    = (int) $opponent['id'];

        $stmt = $conn->prepare(
            'INSERT INTO challenges (challenger_id, opponent_id, topic, question_ids, question_count, time_limit_min, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . CHALLENGE_DAYS . ' DAY))'
        );
        $stmt->bind_param('iissii', $userId, $oppId, $topic, $idList, $count, $timeMin);
        $stmt->execute();
        $newId = (int) $stmt->insert_id;

        $_SESSION['flash'] = 'Challenge sent to ' . $opponent['username'] . '. Play your turn whenever you like.';
        header('Location: challenge.php?id=' . $newId);
        exit;
    }
}

$pageTitle = 'New challenge';
$active    = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="challenges.php">Challenges</a></p>
            <h1>New challenge</h1>
            <p>Both of you get the same questions. Highest score wins.</p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow">
            <form class="form panel" method="post" action="challenge-new.php" novalidate>
                <?php if ($errors): ?>
                    <div class="alert alert-error" role="alert">
                        <ul>
                            <?php foreach ($errors as $msg): ?>
                                <li><?= e($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?= csrf_field() ?>

                <div class="field">
                    <label for="opponent">Who do you want to challenge?</label>
                    <input type="text" id="opponent" name="opponent" maxlength="100"
                           value="<?= e($old['opponent']) ?>" placeholder="Their username" required>
                </div>

                <div class="field">
                    <span class="label">Subjects (tick one or more for a mixed quiz)</span>
                    <div class="check-grid">
                        <?php foreach ($subjects as $slug => $name): ?>
                            <label class="check-pill">
                                <input type="checkbox" name="subjects[]" value="<?= e($slug) ?>"<?= in_array($slug, $old['subjects'], true) ? ' checked' : '' ?>>
                                <span><?= e($name) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="field">
                    <label for="lesson">Or pick one lesson (this overrides the subjects above)</label>
                    <select id="lesson" name="lesson">
                        <option value="0">Any lesson in the subjects I ticked</option>
                        <?php foreach ($subjects as $slug => $name): ?>
                            <?php if (empty($grouped[$slug])) { continue; } ?>
                            <optgroup label="<?= e($name) ?>">
                                <?php foreach ($grouped[$slug] as $l): ?>
                                    <option value="<?= (int) $l['id'] ?>"<?= $old['lesson'] === (int) $l['id'] ? ' selected' : '' ?>><?= e($l['title']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="count">Number of questions (<?= CHALLENGE_MIN_Q ?> to <?= CHALLENGE_MAX_Q ?>)</label>
                        <input type="number" id="count" name="count" min="<?= CHALLENGE_MIN_Q ?>" max="<?= CHALLENGE_MAX_Q ?>"
                               value="<?= (int) $old['count'] ?>" required>
                    </div>
                    <div class="field">
                        <label for="time">Time limit</label>
                        <select id="time" name="time">
                            <?php foreach ($times as $minutes => $label): ?>
                                <option value="<?= $minutes ?>"<?= $old['time'] === $minutes ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="hint">About one minute per question works well.</small>
                    </div>
                </div>

                <p class="muted">
                    Each of you plays in your own time. Your opponent has <?= CHALLENGE_DAYS ?> days to take it.
                    The winner earns <?= XP_CHALLENGE ?> XP (once per opponent per day, within your daily XP limit).
                </p>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Send challenge</button>
                    <a class="btn btn-secondary" href="challenges.php">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
