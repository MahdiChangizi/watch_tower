<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class)) {
    // Use safeLoad so the application can run even if .env is missing.
    \Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

require_once __DIR__ . '/db/db.php';