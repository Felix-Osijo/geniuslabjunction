<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

redirect_if_logged_in();

const GENDERS = [
    'male'              => 'Male',
    'female'            => 'Female',
    'prefer_not_to_say' => 'Prefer not to say',
];

$errors = [];
$old = ['name' => '', 'contact' => '', 'username' => '', 'gender' => 'prefer_not_to_say'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $_) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirmPassword'] ?? '');

    $email = null;
    $phone = null;

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
            $errors[] = 'Enter your name (up to 100 characters).';
        }

        if (strpos($old['contact'], '@') !== false) {
            $email = strtolower($old['contact']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                $errors[] = 'Enter a valid email address.';
            }
        } else {
            $phone = normalize_phone($old['contact']);
            if ($phone === null) {
                $errors[] = 'Enter a valid email address or phone number.';
            }
        }

        if (!isset(GENDERS[$old['gender']])) {
            $errors[] = 'Choose one of the gender options.';
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{2,29}$/', $old['username'])) {
            $errors[] = 'Username must be 3-30 characters, start with a letter, and use only letters, numbers and underscores.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'Password must be 72 characters or fewer.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($_POST['terms']) || empty($_POST['age']) || empty($_POST['guardian'])) {
            $errors[] = 'Please tick all three boxes to continue.';
        }
    }

    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'INSERT INTO users (full_name, email, phone, gender, username, password, consented_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->bind_param('ssssss', $old['name'], $email, $phone, $old['gender'], $old['username'], $hash);
            $stmt->execute();

            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $stmt->insert_id;
            $_SESSION['username'] = $old['username'];
            $_SESSION['role']     = 'user';

            header('Location: feed.php');
            exit;
        } catch (mysqli_sql_exception $ex) {
            if ((int) $ex->getCode() === 1062) {
                $errors[] = 'That username, email or phone number is already registered.';
            } else {
                error_log('Genius Hub register failed: ' . $ex->getMessage());
                $errors[] = 'Something went wrong on our side. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create account';
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
        <h1>Join the community</h1>
        <p>Learn with students, join discussions, complete challenges and earn XP as you grow.</p>
        <ul class="aside-list">
            <li>Interactive learning</li>
            <li>Student collaboration</li>
            <li>XP and achievements</li>
        </ul>
    </aside>

    <section class="auth-panel">
        <form class="form" method="post" action="register.php" novalidate>
            <h2>Create account</h2>
            <p class="form-text">It takes less than a minute.</p>

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
                <label for="name">Name</label>
                <input type="text" id="name" name="name" maxlength="100"
                       value="<?= e($old['name']) ?>" autocomplete="name" required>
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="30"
                       value="<?= e($old['username']) ?>" autocomplete="username" required>
                <small class="hint">3-30 characters, starting with a letter. Letters, numbers and underscores only.</small>
            </div>

            <div class="field">
                <label for="contact">Email or phone number</label>
                <input type="text" id="contact" name="contact" maxlength="150"
                       value="<?= e($old['contact']) ?>" required>
                <small class="hint">Email lets you reset your own password. For a number outside Nigeria, start with + and the country code.</small>
            </div>

            <div class="field">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <?php foreach (GENDERS as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $old['gender'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" maxlength="72"
                           autocomplete="new-password" required>
                    <small class="hint">At least 8 characters.</small>
                </div>
                <div class="field">
                    <label for="confirmPassword">Confirm password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" minlength="8" maxlength="72"
                           autocomplete="new-password" required>
                </div>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="terms" value="1" required>
                <span>I agree to the <a href="privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a>.</span>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="age" value="1" required>
                <span>I am 13 or older.</span>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="guardian" value="1" required>
                <span>If I am under 18, my parent, guardian or school knows I am joining Genius Hub.</span>
            </label>

            <button type="submit" class="btn btn-primary btn-block">Create account</button>

            <p class="auth-switch">
                Already have an account? <a href="login.php">Log in</a>
            </p>
        </form>
    </section>
</main>
<?php
$showFooter = false;
require __DIR__ . '/includes/footer.php';
