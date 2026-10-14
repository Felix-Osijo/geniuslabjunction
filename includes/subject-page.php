<?php
// Shared template for the subject pages in /subjects.
// Each subject file sets $slug, then includes this file.

$subjects = require __DIR__ . '/subjects.php';
$name     = $subjects[$slug] ?? 'Subject';

$base      = '../';
$pageTitle = $name;
$active    = 'home';

require __DIR__ . '/header.php';
?>
<main>
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">Subject</p>
            <h1><?= e($name) ?></h1>
            <p>Lessons, practice questions and discussions for <?= e($name) ?> are on the way.</p>
        </div>
    </section>

    <section class="section">
        <div class="container narrow">
            <div class="panel empty">
                <h2>Coming soon</h2>
                <p>We're still building this subject. In the meantime, explore the other subjects.</p>
                <a class="btn btn-primary" href="../homepage.php#subjects">Back to subjects</a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/footer.php'; ?>
