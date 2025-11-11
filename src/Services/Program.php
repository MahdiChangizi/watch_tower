<?php

namespace App\Services;
use PDO;
require_once __DIR__ . '/../../public/index.php';


class Program {
    public $db = null;

    public function __construct() {
        global $db;
        $this->db = $db;
    }


    public function upsert_program(string $program_name, array $scopes, array $ooscopes, array $config) {
        // prepare json fields
        $scopes_json = json_encode($scopes, JSON_UNESCAPED_UNICODE);
        $ooscopes_json = json_encode($ooscopes, JSON_UNESCAPED_UNICODE);
        $config_json = json_encode($config, JSON_UNESCAPED_UNICODE);

        // check record existence
        $stmt = $this->db->prepare("SELECT * FROM programs WHERE program_name = :program_name LIMIT 1");
        $stmt->execute([':program_name' => $program_name]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // update existing record
            $stmt = $this->db->prepare("UPDATE programs SET scopes = :scopes, ooscopes = :ooscopes, config = :config WHERE program_name = :program_name");
            $stmt->execute([
                ':scopes' => $scopes_json,
                ':ooscopes' => $ooscopes_json,
                ':config' => $config_json,
                ':program_name' => $program_name
            ]);
            return true;
        } else {
            // insert new record
            $stmt = $this->db->prepare("INSERT INTO programs (program_name, scopes, ooscopes, config) VALUES (:program_name, :scopes, :ooscopes, :config)");
            $stmt->execute([
                ':program_name' => $program_name,
                ':scopes' => $scopes_json,
                ':ooscopes' => $ooscopes_json,
                ':config' => $config_json
            ]);
            return true;
        }
    }

    public function get_all_programs(): array {
        $stmt = $this->db->prepare("SELECT * FROM programs");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}