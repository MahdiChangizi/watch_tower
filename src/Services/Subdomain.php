<?php

namespace App\Services;
require_once __DIR__ . '/../../public/index.php';
use App\Tools\SendDiscordMessage;
use App\Helpers\Helpers;
use PDO;

class Subdomain {
    public $db = null;
    public $message = null;
    public $helper = null;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->message = new SendDiscordMessage();
        $this->helper = new Helpers();
    }

    // program_name, subdomain_name, provider
    public function upsert_subdomain(string $program_name, string $subdomain_name, string $provider) {
        $program_name = trim($program_name);
        $subdomain_name = strtolower(trim($subdomain_name));
        $provider = trim($provider);

        if ($program_name === '' || $subdomain_name === '') {
            return;
        }

        // check if record exists
        $stmt = $this->db->prepare("SELECT * FROM subdomains WHERE subdomain = :subdomain LIMIT 1");
        $stmt->execute([':subdomain' => $subdomain_name]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        // if not is_in_scope
        // TODO: implement is_in_scope check
        if ($existing) {
            if (!empty($provider) && ($existing['provider'] ?? '') !== $provider) {
                $stmtUpdate = $this->db->prepare("
                    UPDATE subdomains
                    SET provider = :provider
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':provider' => $provider,
                    ':id' => $existing['id'],
                ]);
            }
            // record exists, no action needed
            return;
        } else {
            // Insert new record
            $stmtInsert = $this->db->prepare("
                INSERT INTO subdomains
                (program_name, subdomain, provider, scope)
                VALUES
                (:program_name, :subdomain, :provider, :scope)
            ");
            
            $stmtInsert->execute([
                ':program_name' => $program_name,
                ':subdomain' => $subdomain_name,
                ':provider' => $provider,
                ':scope' => $program_name
            ]);
        }
    }

    public function get_subdomains_by_program(string $program_name): array {
        $stmt = $this->db->prepare("SELECT * FROM subdomains WHERE program_name = :program_name");
        $stmt->execute([':program_name' => $program_name]);
        

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}