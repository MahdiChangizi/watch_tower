<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

final class Program
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    public function upsert_program(string $program_name, array $scopes, array $ooscopes, array $config): bool
    {
        $program_name = trim($program_name);
        if ($program_name === '') {
            throw new \InvalidArgumentException('Program name cannot be empty.');
        }

        $scopes_json = json_encode($scopes, JSON_UNESCAPED_UNICODE);
        $ooscopes_json = json_encode($ooscopes, JSON_UNESCAPED_UNICODE);
        $config_json = json_encode($config, JSON_UNESCAPED_UNICODE);

        if ($scopes_json === false || $ooscopes_json === false || $config_json === false) {
            throw new \RuntimeException('Failed to encode program data as JSON.');
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM programs WHERE program_name = :program_name LIMIT 1'
            );
            $stmt->execute([':program_name' => $program_name]);
            $exists = (bool) $stmt->fetchColumn();

            if ($exists) {
                $stmt = $this->db->prepare(
                    'UPDATE programs
                     SET scopes = :scopes,
                         ooscopes = :ooscopes,
                         config = :config
                     WHERE program_name = :program_name'
                );
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO programs (program_name, scopes, ooscopes, config)
                     VALUES (:program_name, :scopes, :ooscopes, :config)'
                );
            }

            $stmt->execute([
                ':program_name' => $program_name,
                ':scopes' => $scopes_json,
                ':ooscopes' => $ooscopes_json,
                ':config' => $config_json,
            ]);
        } catch (PDOException $exception) {
            error_log('Program::upsert_program error: ' . $exception->getMessage());
            throw $exception;
        }

        return true;
    }

    public function get_all_programs(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM programs ORDER BY created_date DESC');
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
