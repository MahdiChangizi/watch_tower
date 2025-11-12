<?php
declare(strict_types=1);

final class Database
{
    private static ?\PDO $connection = null;

    public static function connect(): \PDO
    {
        if (self::$connection instanceof \PDO) {
            return self::$connection;
        }

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $dbname = $_ENV['DB_NAME'] ?? 'watch';
        $user = $_ENV['DB_USERNAME'] ?? 'postgres';
        $pass = $_ENV['DB_PASSWORD'] ?? 'mahdi3276';

        try {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);
            $connection = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $exception) {
            throw new \PDOException('Database connection error: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        self::$connection = $connection;

        return self::$connection;
    }

    public static function createTables(): void
    {
        $db = self::connect();
        foreach (['program', 'subdomain', 'live_subdomain', 'http'] as $table) {
            $path = __DIR__ . '/tables/' . $table . '.sql';
            if (!is_file($path)) {
                throw new \RuntimeException(sprintf('Missing schema file: %s', $path));
            }
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new \RuntimeException(sprintf('Unable to read schema file: %s', $path));
            }
            $db->exec($sql);
        }
    }
}
