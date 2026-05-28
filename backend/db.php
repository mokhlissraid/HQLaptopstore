<?php
/**
 * HQ Laptop — database connection (PDO)
 * اسم القاعدة الافتراضي: if0_41646885_hq_laptop
 * للتجاوز دون تعديل الملف: متغيرات البيئة HQ_DB_HOST, HQ_DB_NAME, HQ_DB_USER, HQ_DB_PASS
 */

declare(strict_types=1);

$dbHost = getenv('HQ_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('HQ_DB_NAME') ?: 'if0_41646885_hq_laptop';
$dbUser = getenv('HQ_DB_USER') ?: 'root';
$dbPass = getenv('HQ_DB_PASS') ?: '';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check backend/db.php credentials and that the database exists.');
}
