<?php
// Keep database details and credentials out of browser responses.
ini_set('display_errors', '0');

// Read credentials from the .env file
$env = parse_ini_file(__DIR__ . '/.env');

if ($env === false) {
    error_log('APagri: unable to read database configuration.');
    http_response_code(500);
    exit('Database configuration is unavailable.');
}

$dbHost     = $env['DB_HOST'];
$dbPort     = $env['DB_PORT'];
$dbName     = $env['DB_NAME'];
$dbUser     = $env['DB_USER'];
$dbPassword = $env['DB_PASSWORD'];

try {
    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName;sslmode=require";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('APagri database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
