<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

require_login();

$userId = (int) $_SESSION['user_id'];
$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new     = $_POST['password'] ?? '';
    $confirm = $_POST['confirmPassword'] ?? '';

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$row || !password_verify($current, $row['password'])) {
        $errors[] = 'Your current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif (strlen($new) > 72) {
        $errors[] = 'New password must be 72 characters or fewer.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();

        session_regenerate_id(true);
        $done = true;
    }
}

$pageTitle = 'Account';
$active    = 'account';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow">Account</p>
            <h1>Change password</h1>
            <p>Signed in as <?= e($_SESSION['username'] ?? '') ?>.</p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow">
            <div class="panel">
                <form class="form" method="post" action="account.php" novalidate>
                    <?php if ($done): ?>
                        <div class="alert alert-success" role="status">Password updated.</div>
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

                    <?= csrf_field() ?>

                    <div class="field">
                        <label for="current">Current password</label>
                        <input type="password" id="current" name="current" autocomplete="current-password" required>
                    </div>

                    <div class="field">
                        <label for="password">New password</label>
                        <input type="password" id="password" name="password" minlength="8" maxlength="72"
                               autocomplete="new-password" required>
                        <small class="hint">At least 8 characters.</small>
                    </div>

                    <div class="field">
                        <label for="confirmPassword">Confirm new password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" minlength="8" maxlength="72"
                               autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Update password</button>
                </form>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
