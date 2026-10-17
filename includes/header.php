<?php
// Shared page header + navigation.
// Set these before including: $pageTitle, $active ('home'|'about'|'privacy'),
// $base ('' for root pages, '../' for pages in subfolders),
// $bodyClass, $showNav (false hides the navbar, e.g. on login/register).

require_once __DIR__ . '/auth.php';

$base      = $base      ?? '';
$pageTitle = $pageTitle ?? 'Genius Hub';
$active    = $active    ?? '';
$bodyClass = $bodyClass ?? '';
$showNav   = $showNav   ?? true;

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
    <title><?= e($pageTitle) ?> | Genius Hub</title>
    <link rel="stylesheet" href="<?= e($base) ?>assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if ($showNav): ?>
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
                <?php if (is_logged_in()): ?>
                    <li><a href="<?= e($base) ?>feed.php"<?= nav_current('feed', $active) ?>>Feed</a></li>
                    <li><a href="<?= e($base) ?>account.php"<?= nav_current('account', $active) ?>>Account</a></li>
                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <li><a href="<?= e($base) ?>admin.php"<?= nav_current('admin', $active) ?>>Admin</a></li>
                    <?php endif; ?>
                <?php endif; ?>
                <li><a href="<?= e($base) ?>about.php"<?= nav_current('about', $active) ?>>About</a></li>
                <li><a href="<?= e($base) ?>privacy-policy.php"<?= nav_current('privacy', $active) ?>>Privacy</a></li>
            </ul>
            <div class="nav-actions">
                <?php if (is_logged_in()): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e($base) ?>dashboard.php">Dashboard</a>
                    <a class="btn btn-primary btn-sm" href="<?= e($base) ?>logout.php">Log out</a>
                <?php else: ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e($base) ?>login.php">Log in</a>
                    <a class="btn btn-primary btn-sm" href="<?= e($base) ?>register.php">Get started</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<?php endif; ?>
