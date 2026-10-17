<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/moderation.php';

require_login();
require_admin($conn);

function post_str(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

$editSlug = $_GET['slug'] ?? $_POST['original'] ?? '';
$editSlug = is_string($editSlug) ? $editSlug : '';
$editing  = $editSlug !== '';
$errors   = [];

$subject = ['slug' => '', 'name' => '', 'description' => '', 'sort_order' => 0, 'published' => 1];

if ($editing) {
    $stmt = $conn->prepare('SELECT slug, name, description, sort_order, published FROM subjects WHERE slug = ?');
    $stmt->bind_param('s', $editSlug);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();

    if (!$found) {
        $_SESSION['flash'] = 'That subject could not be found.';
        header('Location: admin-subjects.php');
        exit;
    }
    $subject = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $name        = post_str('name');
    $description = post_str('description');
    $sortOrder   = (int) post_str('sort_order');
    $published   = isset($_POST['published']) ? 1 : 0;
    $slug        = $editing ? $editSlug : strtolower(post_str('slug'));

    if (!$editing && $slug === '') {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        $slug = trim(substr($slug, 0, 30), '-');
    }

    $subject = [
        'slug' => $slug, 'name' => $name, 'description' => $description,
        'sort_order' => $sortOrder, 'published' => $published,
    ];

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Name is required (up to 100 characters).';
    }
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) > 30) {
        $errors[] = 'URL name must use lowercase letters, numbers and hyphens (up to 30 characters).';
    }
    if (mb_strlen($description) > 255) {
        $errors[] = 'Description can be up to 255 characters.';
    }
    if ($sortOrder < 0 || $sortOrder > 9999) {
        $errors[] = 'Order must be between 0 and 9999.';
    }

    if (!$errors) {
        try {
            if ($editing) {
                $stmt = $conn->prepare(
                    'UPDATE subjects SET name = ?, description = ?, sort_order = ?, published = ? WHERE slug = ?'
                );
                $stmt->bind_param('ssiis', $name, $description, $sortOrder, $published, $slug);
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO subjects (slug, name, description, sort_order, published) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('sssii', $slug, $name, $description, $sortOrder, $published);
            }
            $stmt->execute();

            $_SESSION['flash'] = 'Subject saved.';
            header('Location: admin-subjects.php');
            exit;
        } catch (mysqli_sql_exception $ex) {
            if ((int) $ex->getCode() === 1062) {
                $errors[] = 'That URL name is already used by another subject.';
            } else {
                error_log('Genius Hub subject save failed: ' . $ex->getMessage());
                $errors[] = 'Something went wrong while saving. Please try again.';
            }
        }
    }
}

$pageTitle = $editing ? 'Edit subject' : 'New subject';
$active    = 'admin';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="page-hero page-hero-left">
        <div class="container">
            <p class="eyebrow"><a href="admin-subjects.php">Subjects</a></p>
            <h1><?= $editing ? 'Edit subject' : 'New subject' ?></h1>
        </div>
    </section>

    <section class="section section-tight">
        <div class="container narrow">
            <form class="form panel" method="post" action="admin-subject-edit.php" novalidate>
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
                <input type="hidden" name="original" value="<?= e($editSlug) ?>">

                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" maxlength="100" value="<?= e($subject['name']) ?>" required>
                </div>

                <div class="field">
                    <label for="slug">URL name</label>
                    <input type="text" id="slug" name="slug" maxlength="30" value="<?= e($subject['slug']) ?>"
                           <?= $editing ? 'readonly' : 'placeholder="Leave blank to create it from the name"' ?>>
                    <small class="hint">
                        <?= $editing
                            ? 'The URL name cannot be changed after the subject is created.'
                            : 'Lowercase letters, numbers and hyphens, up to 30 characters.' ?>
                    </small>
                </div>

                <div class="field">
                    <label for="description">Short description (shown on the subject page)</label>
                    <input type="text" id="description" name="description" maxlength="255"
                           value="<?= e($subject['description']) ?>">
                </div>

                <div class="field">
                    <label for="sort_order">Order on the homepage</label>
                    <input type="number" id="sort_order" name="sort_order" min="0" max="9999"
                           value="<?= (int) $subject['sort_order'] ?>">
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="published" value="1"<?= $subject['published'] ? ' checked' : '' ?>>
                    <span>Visible to students</span>
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save subject</button>
                    <a class="btn btn-secondary" href="admin-subjects.php">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
