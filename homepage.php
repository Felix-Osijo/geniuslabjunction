<?php
$pageTitle = 'Learn. Connect. Grow.';
$active    = 'home';
require __DIR__ . '/includes/header.php';

$subjects = require __DIR__ . '/includes/subjects.php';
?>
<main>
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-inner">
                <p class="eyebrow eyebrow-light">Student learning community</p>
                <h1>Learn. Connect. Grow.</h1>
                <p class="lead">
                    Join students across different schools, learn together, solve problems,
                    earn XP and become part of a growing educational community.
                </p>
                <div class="hero-actions">
                    <?php if (is_logged_in()): ?>
                        <a class="btn btn-primary btn-lg" href="dashboard.php">Go to dashboard</a>
                    <?php else: ?>
                        <a class="btn btn-primary btn-lg" href="register.php">Get started free</a>
                        <a class="btn btn-light btn-lg" href="login.php">Log in</a>
                    <?php endif; ?>
                </div>
            </div>
            <img class="hero-art" src="assets/images/genius-hub-cover.png" alt="Genius Hub cover graphic">
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-head">
                <h2>Why Genius Hub?</h2>
                <p>Everything you need to study smarter, together.</p>
            </div>
            <div class="feature-grid">
                <article class="card">
                    <span class="card-icon" aria-hidden="true">📚</span>
                    <h3>Study materials</h3>
                    <p>Access tutorials, lessons and educational resources across multiple subjects.</p>
                </article>
                <article class="card">
                    <span class="card-icon" aria-hidden="true">🌍</span>
                    <h3>Student community</h3>
                    <p>Connect with students from different schools, collaborate and share ideas.</p>
                </article>
                <article class="card">
                    <span class="card-icon" aria-hidden="true">🏆</span>
                    <h3>XP and rewards</h3>
                    <p>Earn points by learning, participating, helping others and completing challenges.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section section-alt" id="subjects">
        <div class="container">
            <div class="section-head">
                <h2>Popular subjects</h2>
                <p>Pick a subject and start learning.</p>
            </div>
            <div class="subject-grid">
                <?php foreach ($subjects as $slug => $name): ?>
                    <a class="subject-card" href="subject.php?s=<?= e(urlencode($slug)) ?>"><?= e($name) ?></a>
                <?php endforeach; ?>
                <span class="subject-card is-soon">
                    Genius Hub Campus <small class="tag">Coming soon</small>
                </span>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="cta-band">
                <h2>Start your journey</h2>
                <p>Create your account and begin earning XP today.</p>
                <div class="hero-actions">
                    <?php if (is_logged_in()): ?>
                        <a class="btn btn-light btn-lg" href="dashboard.php">Go to dashboard</a>
                    <?php else: ?>
                        <a class="btn btn-light btn-lg" href="register.php">Create account</a>
                        <a class="btn btn-ghost btn-lg" href="login.php">Log in</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
