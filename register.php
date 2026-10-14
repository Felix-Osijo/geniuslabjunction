<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

redirect_if_logged_in();

const MIN_AGE = 13; // change this if your audience is different

$errors = [];
$old = [
    'surname'    => '',
    'othernames' => '',
    'dob'        => '',
    'gender'     => '',
    'email'      => '',
    'username'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }
    $old['email'] = strtolower($old['email']);
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirmPassword'] ?? '';

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        if ($old['surname'] === '' || mb_strlen($old['surname']) > 100) {
            $errors[] = 'Enter your surname (up to 100 characters).';
        }
        if ($old['othernames'] === '' || mb_strlen($old['othernames']) > 100) {
            $errors[] = 'Enter your other names (up to 100 characters).';
        }

        $dob = DateTime::createFromFormat('Y-m-d', $old['dob']);
        if (!$dob || $dob->format('Y-m-d') !== $old['dob']) {
            $errors[] = 'Enter a valid date of birth.';
        } else {
            $age = $dob->diff(new DateTime('today'))->y;
            if ($dob > new DateTime('today') || $age > 100) {
                $errors[] = 'Enter a valid date of birth.';
            } elseif ($age < MIN_AGE) {
                $errors[] = 'You must be at least ' . MIN_AGE . ' years old to join.';
            }
        }

        if (!in_array($old['gender'], ['male', 'female'], true)) {
            $errors[] = 'Select your gender.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 150) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $old['username'])) {
            $errors[] = 'Username must be 3-30 characters: letters, numbers and underscores only.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'Password must be 72 characters or fewer.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($_POST['terms'])) {
            $errors[] = 'You must accept the Privacy Policy to continue.';
        }
    }

    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'INSERT INTO users (surname, othernames, dob, gender, email, username, password)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'sssssss',
                $old['surname'], $old['othernames'], $old['dob'], $old['gender'],
                $old['email'], $old['username'], $hash
            );
            $stmt->execute();

            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $stmt->insert_id;
            $_SESSION['username'] = $old['username'];

            header('Location: dashboard.php');
            exit;
        } catch (mysqli_sql_exception $ex) {
            if ((int) $ex->getCode() === 1062) { // duplicate email or username
                $errors[] = 'That email or username is already taken.';
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
            <p class="form-text">Start your Genius Hub journey.</p>

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

            <div class="field-row">
                <div class="field">
                    <label for="surname">Surname</label>
                    <input type="text" id="surname" name="surname" maxlength="100"
                           value="<?= e($old['surname']) ?>" autocomplete="family-name" required>
                </div>
                <div class="field">
                    <label for="othernames">Other names</label>
                    <input type="text" id="othernames" name="othernames" maxlength="100"
                           value="<?= e($old['othernames']) ?>" autocomplete="given-name" required>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="dob">Date of birth</label>
                    <input type="date" id="dob" name="dob"
                           value="<?= e($old['dob']) ?>" autocomplete="bday" required>
                </div>
                <div class="field">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select</option>
                        <option value="male"<?= $old['gender'] === 'male' ? ' selected' : '' ?>>Male</option>
                        <option value="female"<?= $old['gender'] === 'female' ? ' selected' : '' ?>>Female</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="150"
                       value="<?= e($old['email']) ?>" autocomplete="email" required>
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="30"
                       value="<?= e($old['username']) ?>" autocomplete="username" required>
                <small class="hint">Letters, numbers and underscores. 3-30 characters.</small>
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
                <span>I agree to the <a href="privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a></span>
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
