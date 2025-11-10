<?php

use PDO\PDOException;


class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection === null) {
            
            $host = $_ENV['DB_HOST'];
            $port = $_ENV['DB_PORT'];
            $dbname = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USERNAME'];
            $pass = $_ENV['DB_PASSWORD'];

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
