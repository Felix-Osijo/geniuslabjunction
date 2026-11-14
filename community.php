<?php
require __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Community';
$active    = 'community';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow">Coming soon</p>
            <h1>Community</h1>
            <p>Classes run by teachers and schools, right inside Genius Hub.</p>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow">
            <div class="panel stack">
                <h2>What's coming</h2>
                <ul class="plain-list">
                    <li>Join your class with a code from your teacher or school.</li>
                    <li>Follow a series of sessions with lessons, quizzes and announcements.</li>
                    <li>Take lessons and quizzes your teacher made just for your class.</li>
                    <li>Talk with your classmates in a private class feed.</li>
                </ul>
                <p class="muted">Teachers and schools: we will open applications soon.</p>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
