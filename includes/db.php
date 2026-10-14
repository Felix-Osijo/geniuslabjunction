<?php
// Database connection. Credentials live in includes/config.php (git-ignored).

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    error_log('Genius Hub: includes/config.php is missing.');
    http_response_code(500);
    exit('Server is not configured yet.');
}

$config = require $configFile;

// Throw exceptions instead of printing raw database errors to visitors.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name']
    );
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $ex) {
    error_log('Genius Hub DB connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable. Please try again shortly.');
}

unset($config, $configFile);
