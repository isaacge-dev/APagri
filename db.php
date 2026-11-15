<?php
// Keep database details and credentials out of browser responses.
ini_set('display_errors', '0');

// Load fallback variables from local .env if present
$envFile = __DIR__ . '/.env';
$localEnv = (file_exists($envFile) && is_readable($envFile)) ? @parse_ini_file($envFile) : [];
if ($localEnv === false) {
    $localEnv = [];
}

// Retrieve configuration variable: check getenv() -> $_ENV -> $_SERVER -> local .env -> default
$getConf = function (string $key, ?string $default = null) use ($localEnv): ?string {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return (string)$val;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string)$_SERVER[$key];
    }
    if (isset($localEnv[$key]) && $localEnv[$key] !== '') {
        return (string)$localEnv[$key];
    }
    return $default;
};

$dbHost     = $getConf('DB_HOST');
$dbPort     = $getConf('DB_PORT', '5432');
$dbName     = $getConf('DB_NAME', 'postgres');
$dbUser     = $getConf('DB_USER');
$dbPassword = $getConf('DB_PASSWORD');
$dbSslMode  = $getConf('DB_SSLMODE', 'require');

if (empty($dbHost) || empty($dbName) || empty($dbUser) || $dbPassword === null || $dbPassword === '') {
    error_log('APagri: database configuration is missing or incomplete (DB_HOST, DB_NAME, DB_USER, or DB_PASSWORD not set).');
    http_response_code(500);
    exit('Database configuration is unavailable.');
}

try {
    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};sslmode={$dbSslMode}";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Log technical error securely to server logs; do not expose credentials/DSN to client
    error_log('APagri database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please contact the administrator.');
}
