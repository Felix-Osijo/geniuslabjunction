<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/mailer.php';

redirect_if_logged_in();

$error = '';
$sent  = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $stmt = $conn->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $userId = (int) $user['id'];

            $stmt = $conn->prepare(
                'SELECT COUNT(*) FROM password_resets
                 WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $recent = (int) $stmt->get_result()->fetch_row()[0];

            if ($recent < 3) {
                $token = bin2hex(random_bytes(32));
                $hash  = hash('sha256', $token);

                $stmt = $conn->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
                );
                $stmt->bind_param('is', $userId, $hash);
                $stmt->execute();

                send_reset_email($user['email'], app_url() . '/reset-password.php?token=' . $token);
            }
        }

        $sent = true;
    }
}

$pageTitle = 'Forgot password';
$bodyClass = 'auth-body';
$showNav   = false;
require __DIR__ . '/includes/header.php';
?>
<main class="auth-shell auth-shell-narrow">
    <section class="auth-panel">
        <?php if ($sent): ?>
            <div class="form">
                <h2>Check your email</h2>
                <div class="alert alert-success" role="status">
                    If an account exists for that email, we've sent a reset link. It works for 1 hour.
                </div>
                <a class="btn btn-primary btn-block" href="login.php">Back to log in</a>
            </div>
        <?php else: ?>
            <form class="form" method="post" action="forgot-password.php" novalidate>
                <h2>Forgot password?</h2>
                <p class="form-text">Enter your email and we'll send you a reset link.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <?= csrf_field() ?>

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>"
                           autocomplete="email" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Send reset link</button>

                <p class="auth-switch"><a href="login.php">Back to log in</a></p>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php
$showFooter = false;
require __DIR__ . '/includes/footer.php';
