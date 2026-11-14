<?php
require_once __DIR__ . '/auth.php';

global $conn;

$base      = $base      ?? '';
$pageTitle = $pageTitle ?? 'Genius Hub';
$active    = $active    ?? '';
$bodyClass = $bodyClass ?? '';
$showNav   = $showNav   ?? true;

$viewer = null;
if ($showNav && is_logged_in()) {
    if (!isset($conn)) {
        require_once __DIR__ . '/db.php';
    }

    $viewerId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT username, xp, level, role FROM users WHERE id = ?');
    $stmt->bind_param('i', $viewerId);
    $stmt->execute();
    $viewer = $stmt->get_result()->fetch_assoc();
}

$appShell = $viewer !== null;
if ($appShell) {
    $bodyClass = trim($bodyClass . ' has-tabbar');
}

if (!function_exists('nav_current')) {
    function nav_current(string $key, string $active): string
    {
        return $key === $active ? ' aria-current="page"' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#072c4e">
    <title><?= e($pageTitle) ?> | Genius Hub</title>
    <link rel="stylesheet" href="<?= e($base) ?>assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if ($showNav && $appShell): ?>
    <?php
    $tabs = [
        'feed'      => ['feed.php', 'Home',
            '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>'],
        'subjects'  => ['subjects.php', 'Subjects',
            '<path d="M4 19.5V5a2 2 0 0 1 2-2h14v14H6a2 2 0 0 0-2 2.5z"/><path d="M6 17h14"/>'],
        'community' => ['community.php', 'Community',
            '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M17 14.2c2.6.2 4.5 2 4.5 5"/>'],
        'dashboard' => ['dashboard.php', 'Dashboard',
            '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
    ];
    ?>
<header class="app-header">
    <div class="app-bar container">
        <a class="user-badge" href="<?= e($base) ?>profile.php?u=<?= e(urlencode($viewer['username'])) ?>" aria-label="Your profile">
            <span class="avatar" aria-hidden="true"><?= e(strtoupper(mb_substr($viewer['username'], 0, 1))) ?></span>
            <span class="user-badge-text">
                <strong><?= e($viewer['username']) ?></strong>
                <span class="xp-pill"><?= number_format((int) $viewer['xp']) ?> XP</span>
            </span>
        </a>

        <nav class="app-tabs" aria-label="Main">
            <?php foreach ($tabs as $key => [$file, $label, $icon]): ?>
                <a href="<?= e($base . $file) ?>"<?= nav_current($key, $active) ?>>
                    <svg class="tab-icon" viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <details class="menu">
            <summary aria-label="Menu">&#9776;</summary>
            <div class="menu-panel">
                <a href="<?= e($base) ?>account.php">Account</a>
                <?php if ($viewer['role'] === 'admin'): ?>
                    <a href="<?= e($base) ?>admin.php">Admin</a>
                <?php endif; ?>
                <a href="<?= e($base) ?>about.php">About</a>
                <a href="<?= e($base) ?>privacy-policy.php">Privacy</a>
                <a class="menu-danger" href="<?= e($base) ?>logout.php">Log out</a>
            </div>
        </details>
    </div>
</header>
<?php elseif ($showNav): ?>
<header class="site-header">
    <nav class="navbar container" aria-label="Main">
        <a class="brand" href="<?= e($base) ?>homepage.php">
            <span class="brand-mark" aria-hidden="true">G</span>
            <span>Genius Hub</span>
        </a>

        <button class="nav-toggle" type="button" aria-label="Toggle menu"
                aria-expanded="false" aria-controls="nav-menu">
            <span></span><span></span><span></span>
        </button>

        <div class="nav-menu" id="nav-menu">
            <ul class="nav-links">
                <li><a href="<?= e($base) ?>homepage.php"<?= nav_current('home', $active) ?>>Home</a></li>
                <li><a href="<?= e($base) ?>about.php"<?= nav_current('about', $active) ?>>About</a></li>
                <li><a href="<?= e($base) ?>privacy-policy.php"<?= nav_current('privacy', $active) ?>>Privacy</a></li>
            </ul>
            <div class="nav-actions">
                <a class="btn btn-secondary btn-sm" href="<?= e($base) ?>login.php">Log in</a>
                <a class="btn btn-primary btn-sm" href="<?= e($base) ?>register.php">Get started</a>
            </div>
        </div>
    </nav>
</header>
<?php endif; ?>
