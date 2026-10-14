<?php
// Shared footer. Set $showFooter = false on pages that don't want it.
$base       = $base       ?? '';
$showFooter = $showFooter ?? true;
?>
<?php if ($showFooter): ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand brand-light" href="<?= e($base) ?>homepage.php">
                <span class="brand-mark" aria-hidden="true">G</span>
                <span>Genius Hub</span>
            </a>
            <p class="footer-note">Learn together. Earn XP. Grow every day.</p>
        </div>
        <ul class="footer-links">
            <li><a href="<?= e($base) ?>about.php">About</a></li>
            <li><a href="<?= e($base) ?>privacy-policy.php">Privacy Policy</a></li>
            <li><a href="<?= e($base) ?>register.php">Create account</a></li>
        </ul>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> Genius Hub. All rights reserved.</p>
    </div>
</footer>
<?php endif; ?>
<script src="<?= e($base) ?>assets/js/main.js" defer></script>
</body>
</html>
