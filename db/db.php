<?php

use PDO\PDOException;


class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection === null) {

            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '5432';
            $dbname = $_ENV['DB_NAME'] ?? 'watch';
            $user = $_ENV['DB_USERNAME'] ?? 'postgres';
            $pass = $_ENV['DB_PASSWORD'] ?? 'mahdi3276';

            try {
                self::$connection = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Database connection error: " . $e->getMessage());
            }
        }

        return self::$connection;
    }

    public static function createTables() {
        $db = self::connect();
        $db->exec(file_get_contents(__DIR__ . '/tables/program.sql'));
        $db->exec(file_get_contents(__DIR__ . '/tables/subdomain.sql'));
        $db->exec(file_get_contents(__DIR__ . '/tables/live_subdomain.sql'));
        $db->exec(file_get_contents(__DIR__ . '/tables/http.sql'));
    }
}
