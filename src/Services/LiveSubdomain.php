<?php

namespace App\Services;

use App\Tools\SendDiscordMessage;
use App\Helpers\Helpers;
use PDO;
use PDOException;
require_once __DIR__ . '/../../public/index.php';

class LiveSubdomain {
    public $db = null;
    public $message = null;
    public $helper = null;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->message = new SendDiscordMessage();
        $this->helper = new Helpers();
    }

    public function upsert_lives(string $domain, string $subdomain, string $scope, array $ips, array $cdn): bool
    {
        try {
            // Find the program related to the domain using jsonb containment
            $stmtProgram = $this->db->prepare("
                SELECT program_name
                FROM programs
                WHERE scopes @> :scope_json::jsonb
                LIMIT 1
            ");
            $stmtProgram->execute([':scope_json' => json_encode([$domain])]);
            $programRow = $stmtProgram->fetch(PDO::FETCH_ASSOC);
            $programName = $programRow['program_name'] ?? $domain;

            // Check for existing record
            $stmt = $this->db->prepare("SELECT * FROM live_subdomains WHERE subdomain = :subdomain LIMIT 1");
            $stmt->execute([':subdomain' => $subdomain]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            // Sort and remove duplicates
            $ips_sorted = array_values(array_unique(array_filter($ips, fn($v) => is_string($v) && $v !== '')));
            sort($ips_sorted);

            $cdn_sorted = array_values(array_unique(array_filter($cdn, fn($v) => is_string($v) && $v !== '')));
            sort($cdn_sorted);

            $now = (new \DateTime())->format('Y-m-d H:i:s');

            if ($existing) {
                $existing_ips = json_decode($existing['ips'], true) ?: [];
                $existing_cdn = json_decode($existing['cdn'], true) ?: [];

                sort($existing_ips);
                sort($existing_cdn);

                $ips_changed = ($existing_ips !== $ips_sorted);
                $cdn_changed = ($existing_cdn !== $cdn_sorted);

                if ($ips_changed || $cdn_changed) {
                    $stmtUpdate = $this->db->prepare("
                        UPDATE live_subdomains SET
                            program_name = :program_name,
                            scope = :scope,
                            ips = :ips::jsonb,
                            cdn = :cdn::jsonb,
                            last_update = :last_update
                        WHERE subdomain = :subdomain
                    ");
                    $stmtUpdate->execute([
                        ':program_name' => $programName,
                        ':scope' => $domain,
                        ':ips' => json_encode($ips_sorted),
                        ':cdn' => json_encode($cdn_sorted),
                        ':last_update' => $now,
                        ':subdomain' => $subdomain
                    ]);

                    echo "[+] [".$this->helper::current_time()."] Updated live subdomain:" . "\033[0;32m" ." {$subdomain}\n" . "\033[0m";
                } else {
                    // Only update last_update
                    $stmtUpdate = $this->db->prepare("
                        UPDATE live_subdomains SET
                            last_update = :last_update
                        WHERE subdomain = :subdomain
                    ");
                    $stmtUpdate->execute([
                        ':last_update' => $now,
                        ':subdomain' => $subdomain
                    ]);
                }
            } else {
                // Insert new record
                $stmtInsert = $this->db->prepare("
                    INSERT INTO live_subdomains
                    (program_name, subdomain, scope, ips, cdn, created_date, last_update)
                    VALUES
                    (:program_name, :subdomain, :scope, :ips::jsonb, :cdn::jsonb, :created_date, :last_update)
                ");
                $stmtInsert->execute([
                    ':program_name' => $programName,
                    ':subdomain' => $subdomain,
                    ':scope' => $domain,
                    ':ips' => json_encode($ips_sorted),
                    ':cdn' => json_encode($cdn_sorted),
                    ':created_date' => $now,
                    ':last_update' => $now
                ]);

                $this->message->send("```'$subdomain' (fresh live) has been added to '$programName' program```");
            }

            return true;
        } catch (PDOException $e) {
            error_log("LiveSubdomain::upsert_lives PDOException: " . $e->getMessage());
            return false;
        } catch (\Throwable $t) {
            error_log("LiveSubdomain::upsert_lives error: " . $t->getMessage());
            return false;
        }
    }

    public function getAllLives(): array
    {
        # just fetch subdomain from live_subdomains
        $stmt = $this->db->prepare("SELECT * FROM live_subdomains ORDER BY last_update DESC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
