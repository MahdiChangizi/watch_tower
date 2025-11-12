<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Helpers;
use App\Tools\SendDiscordMessage;
use PDO;
use PDOException;

final class Http
{
    private PDO $db;
    private Helpers $helper;
    private ?SendDiscordMessage $messenger;

    public function __construct(?PDO $db = null, ?Helpers $helper = null, ?SendDiscordMessage $messenger = null)
    {
        $this->db = $db ?? \Database::connect();
        $this->helper = $helper ?? new Helpers();
        $this->messenger = $messenger ?? SendDiscordMessage::createOrNull();
    }

    public function upsert_http(
        string $program_name,
        string $subdomain,
        array $ips,
        array $tech,
        string $title,
        int $status_code,
        array $headers,
        string $url,
        string $final_url,
        string $favicon
    ): bool {
        $program_name = trim($program_name);
        $subdomain = strtolower(trim($subdomain));

        if ($program_name === '' || $subdomain === '') {
            return false;
        }

        $scope = $this->extractScope($subdomain);
        $now = Helpers::current_time();

        $ips_json = json_encode($ips, JSON_UNESCAPED_UNICODE);
        $tech_json = json_encode($tech, JSON_UNESCAPED_UNICODE);
        $headers_json = json_encode($headers, JSON_UNESCAPED_UNICODE);

        if ($ips_json === false || $tech_json === false || $headers_json === false) {
            throw new \RuntimeException('Failed to encode http metadata as JSON.');
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT title, status_code, favicon FROM http WHERE program_name = :program_name AND subdomain = :subdomain LIMIT 1'
            );
            $stmt->execute([
                ':program_name' => $program_name,
                ':subdomain' => $subdomain,
            ]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $this->notifyOnChanges($existing, $subdomain, $title, $status_code, $favicon);

                $update = $this->db->prepare(
                    'UPDATE http SET 
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
                     WHERE program_name = :program_name AND subdomain = :subdomain'
                );

                $update->execute([
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
                    ':program_name' => $program_name,
                    ':subdomain' => $subdomain,
                ]);

                return true;
            }

            $insert = $this->db->prepare(
                'INSERT INTO http (
                    program_name, subdomain, scope, ips, tech, title, status_code,
                    headers, url, final_url, favicon, created_date, last_update
                ) VALUES (
                    :program_name, :subdomain, :scope, :ips::jsonb, :tech::jsonb, :title,
                    :status_code, :headers::jsonb, :url, :final_url, :favicon, :created_date, :last_update
                )'
            );

            $insert->execute([
                ':program_name' => $program_name,
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

            if ($this->messenger) {
                $this->messenger->send(
                    sprintf(
                        "```%s (fresh http) added to '%s'```",
                        $subdomain,
                        $program_name
                    )
                );
            }

            error_log(sprintf('[%s] Inserted new http service: %s', $now, $subdomain));

            return true;
        } catch (PDOException $exception) {
            error_log('Http::upsert_http error: ' . $exception->getMessage());
            return false;
        }
    }

    private function extractScope(string $subdomain): string
    {
        $parts = explode('.', $subdomain);
        return count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $subdomain;
    }

    private function notifyOnChanges(array $existing, string $subdomain, string $title, int $status_code, string $favicon): void
    {
        if (!$this->messenger) {
            return;
        }

        $changes = [];

        if (($existing['title'] ?? '') !== $title) {
            $changes[] = sprintf("title: '%s' -> '%s'", $existing['title'] ?? '', $title);
        }

        if ((int) ($existing['status_code'] ?? 0) !== $status_code) {
            $changes[] = sprintf('status: %s -> %d', $existing['status_code'] ?? 'n/a', $status_code);
        }

        if (($existing['favicon'] ?? '') !== $favicon) {
            $changes[] = sprintf("favhash: '%s' -> '%s'", $existing['favicon'] ?? '', $favicon);
        }

        if (!$changes) {
            return;
        }

        $message = sprintf("```%s\n%s```", $subdomain, implode("\n", $changes));
        $this->messenger->send($message);
    }
}
