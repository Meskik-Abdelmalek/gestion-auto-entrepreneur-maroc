<?php
// ── Database Configuration ────────────────────────────────────
// Copy this file to config.php and update with your credentials
define('DB_HOST',    getenv('AE_DB_HOST')    ?: 'localhost');
define('DB_USER',    getenv('AE_DB_USER')    ?: 'root');
define('DB_PASS',    getenv('AE_DB_PASS')    ?: '');
define('DB_NAME',    getenv('AE_DB_NAME')    ?: 'moroccan_ae');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'Moroccan AE System');
define('APP_VERSION', '1.0.0');
define('APP_LOCALE',  'fr_MA');
define('APP_TIMEZONE','Africa/Casablanca');

date_default_timezone_set(APP_TIMEZONE);
setlocale(LC_TIME, APP_LOCALE . '.UTF-8', APP_LOCALE, 'fr_FR.UTF-8', 'fr_FR');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
