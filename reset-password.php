<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

redirect_if_logged_in();

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = is_string($token) ? $token : '';
$reset = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare(
        'SELECT user_id FROM password_resets
         WHERE token_hash = ? AND used = 0 AND expires_at > NOW() LIMIT 1'
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
}

$errors = [];

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirmPassword'] ?? '';

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password must be 72 characters or fewer.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $userId  = (int) $reset['user_id'];
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $newHash, $userId);
            $stmt->execute();

            $stmt = $conn->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();

            $conn->commit();
        } catch (Throwable $ex) {
            $conn->rollback();
            throw $ex;
        }

        $_SESSION['notice'] = 'Password updated. Log in with your new password.';
        header('Location: login.php');
        exit;
    }
}

$pageTitle = 'Reset password';
$bodyClass = 'auth-body';
$showNav   = false;
require __DIR__ . '/includes/header.php';
?>
<main class="auth-shell auth-shell-narrow">
    <section class="auth-panel">
        <?php if (!$reset): ?>
            <div class="form">
                <h2>Link expired</h2>
                <div class="alert alert-error" role="alert">
                    This reset link is invalid or has expired.
                </div>
                <a class="btn btn-primary btn-block" href="forgot-password.php">Request a new link</a>
            </div>
        <?php else: ?>
            <form class="form" method="post" action="reset-password.php" novalidate>
                <h2>Choose a new password</h2>

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
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="field">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" minlength="8" maxlength="72"
                           autocomplete="new-password" required autofocus>
                    <small class="hint">At least 8 characters.</small>
                </div>

                <div class="field">
                    <label for="confirmPassword">Confirm new password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" minlength="8" maxlength="72"
                           autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Update password</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php
$showFooter = false;
require __DIR__ . '/includes/footer.php';
