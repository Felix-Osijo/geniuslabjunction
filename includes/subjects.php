<?php
require_once __DIR__ . '/db.php';

$subjectList = [];
$result = $conn->query('SELECT slug, name FROM subjects WHERE published = 1 ORDER BY sort_order, name');
foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
    $subjectList[$row['slug']] = $row['name'];
}

return $subjectList;
