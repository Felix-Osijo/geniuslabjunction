<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/throttle.php';

redirect_if_logged_in();

const DUMMY_HASH = '$2b$10$abcdefghijklmnopqrstuuWZOWHCdnuOVFb9VpY4mOQM06pyJg9PK';

$error      = '';
$identifier = '';
$ip         = $_SERVER['REMOTE_ADDR'] ?? '';
$notice     = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $key        = mb_substr(strtolower($identifier), 0, 150);

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Enter your email or username and your password.';
    } elseif (login_locked($conn, $key, $ip)) {
        $error = 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MIN . ' minutes and try again.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, username, password, role FROM users
             WHERE email = ? OR username = ? LIMIT 1'
        );
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $hash  = $user['password'] ?? DUMMY_HASH;
        $valid = password_verify($password, $hash) && $user !== null;

        if ($valid) {
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $upd->bind_param('si', $newHash, $user['id']);
                $upd->execute();
            }

            clear_login_failures($conn, $key);
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            header('Location: dashboard.php');
            exit;
        }

        record_login_failure($conn, $key, $ip);
        $error = 'Incorrect email/username or password.';
    }
}

$pageTitle = 'Log in';
$bodyClass = 'auth-body';
$showNav   = false;
require __DIR__ . '/includes/header.php';
?>
<main class="auth-shell">
    <aside class="auth-aside">
        <a class="brand brand-light" href="homepage.php">
            <span class="brand-mark" aria-hidden="true">G</span>
            <span>Genius Hub</span>
        </a>
        <h1>Welcome back</h1>
        <p>Pick up where you left off: learn, connect with students and keep your XP climbing.</p>
        <ul class="aside-list">
            <li>Learn new skills</li>
            <li>Connect with students</li>
            <li>Earn XP and rewards</li>
        </ul>
    </aside>

    <section class="auth-panel">
        <form class="form" method="post" action="login.php" novalidate>
            <h2>Log in</h2>
            <p class="form-text">Access your Genius Hub account.</p>

            <?php if ($notice !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($notice) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?= csrf_field() ?>

            <div class="field">
                <label for="identifier">Email or username</label>
                <input type="text" id="identifier" name="identifier"
                       value="<?= e($identifier) ?>"
                       autocomplete="username" required autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <a class="forgot" href="forgot-password.php">Forgot password?</a>

            <button type="submit" class="btn btn-primary btn-block">Log in</button>

            <p class="auth-switch">
                New here? <a href="register.php">Create an account</a>
            </p>
        </form>
    </section>
</main>
<?php
$showFooter = false;
require __DIR__ . '/includes/footer.php';
