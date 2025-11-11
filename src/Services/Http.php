<?php
namespace App\Services;

use App\Tools\SendDiscordMessage;
use App\Helpers\Helpers;
require_once __DIR__ . '/../../public/index.php';


class Http {
    public $db = null;
    public $message = null;
    public $helper = null;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->message = new SendDiscordMessage();
        $this->helper = new Helpers();
    }

    public function upsert_http(
        string $subdomain,
        string $scope,
        array $ips,
        array $tech,
        string $title,
        int $status_code,
        array $headers,
        string $url,
        string $final_url,
        string $favicon
    ): bool {
        $now = Helpers::current_time();

        // get program name related to scope
        $scopeJson = json_encode([$scope]); // e.g. '["example.com"]'
        $stmt = $this->db->prepare("SELECT program_name FROM programs WHERE scopes @> :scope_json::jsonb LIMIT 1");
        $stmt->execute([':scope_json' => $scopeJson]);
        $programRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        $programName = $programRow['program_name'] ?? $scope;


        // prepare json fields
        $ips_json = json_encode($ips, JSON_UNESCAPED_UNICODE);
        $tech_json = json_encode($tech, JSON_UNESCAPED_UNICODE);
        $headers_json = json_encode($headers, JSON_UNESCAPED_UNICODE);

        // 2) check record existence
        $stmt = $this->db->prepare("SELECT * FROM http WHERE subdomain = :subdomain LIMIT 1");
        $stmt->execute([':subdomain' => $subdomain]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            // check for changes
            if (($existing['title'] ?? '') !== $title) {
                $this->message->send("```'{$subdomain}' title changed from '{$existing['title']}' to '{$title}'```");
                error_log("[".$this->helper::current_time()."] changed title for subdomain: {$subdomain}");
            }

            if ((int)$existing['status_code'] !== $status_code) {
                $this->message->send("```'{$subdomain}' status code changed from '{$existing['status_code']}' to '{$status_code}'```");
                error_log("[".$this->helper::current_time()."] changed status code for subdomain: {$subdomain}");
            }

            if (($existing['favicon'] ?? '') !== $favicon) {
                $this->message->send("```'{$subdomain}' favhash changed from '{$existing['favicon']}' to '{$favicon}'```");
                error_log("[".$this->helper::current_time()."] changed favhash for subdomain: {$subdomain}");
            }

            // update records
            $sqlUpdate = "UPDATE http SET 
                program_name = :program_name,
                scope = :scope,
                ips = :ips::jsonb,
                tech = :tech::jsonb,
                title = :title,
                status_code = :status_code,
                headers = :headers::jsonb,
                url = :url,
                final_url = :final_url,
                favicon = :favicon,
                last_update = :last_update
                WHERE subdomain = :subdomain";
            $stmt = $this->db->prepare($sqlUpdate);
            $stmt->execute([
                ':program_name' => $programName,
                ':scope' => $scope,
                ':ips' => $ips_json,
                ':tech' => $tech_json,
                ':title' => $title,
                ':status_code' => $status_code,
                ':headers' => $headers_json,
                ':url' => $url,
                ':final_url' => $final_url,
                ':favicon' => $favicon,
                ':last_update' => $now,
                ':subdomain' => $subdomain,
            ]);
            return true;
        } else {
            // new record
            $sqlInsert = "INSERT INTO http (
                program_name, subdomain, scope, ips, tech, title, status_code,
                headers, url, final_url, favicon, created_date, last_update
            ) VALUES (
                :program_name, :subdomain, :scope, :ips::jsonb, :tech::jsonb, :title,
                :status_code, :headers::jsonb, :url, :final_url, :favicon, :created_date, :last_update
            )";
            $stmt = $this->db->prepare($sqlInsert);
            $stmt->execute([
                ':program_name' => $programName,
                ':subdomain' => $subdomain,
                ':scope' => $scope,
                ':ips' => $ips_json,
                ':tech' => $tech_json,
                ':title' => $title,
                ':status_code' => $status_code,
                ':headers' => $headers_json,
                ':url' => $url,
                ':final_url' => $final_url,
                ':favicon' => $favicon,
                ':created_date' => $now,
                ':last_update' => $now,
            ]);

            $this->message->send("```'{$subdomain}' (fresh http) has been added to '{$programName}' program```");
            error_log("[".$this->helper::current_time()."] Inserted new http service: {$subdomain}");
            return true;
        }
    }
}