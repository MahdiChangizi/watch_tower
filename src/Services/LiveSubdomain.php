<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Helpers;
use App\Tools\SendDiscordMessage;
use PDO;
use PDOException;

final class LiveSubdomain
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

    public function upsert_lives(string $program_name, string $subdomain, array $ips, array $cdn): bool
    {
        $program_name = trim($program_name);
        $subdomain = strtolower(trim($subdomain));

        if ($program_name === '' || $subdomain === '') {
            return false;
        }

        $scope = $this->extractScope($subdomain);
        $ips_sorted = $this->normalizeAndSort($ips);
        $cdn_sorted = $this->normalizeAndSort($cdn);
        $now = Helpers::current_time();

        try {
            $stmt = $this->db->prepare(
                'SELECT ips, cdn FROM live_subdomains WHERE subdomain = :subdomain AND program_name = :program_name LIMIT 1'
            );
            $stmt->execute([
                ':subdomain' => $subdomain,
                ':program_name' => $program_name,
            ]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $existing_ips = $this->normalizeAndSort(json_decode($existing['ips'] ?? '[]', true) ?: []);
                $existing_cdn = $this->normalizeAndSort(json_decode($existing['cdn'] ?? '[]', true) ?: []);

                $changesDetected = $existing_ips !== $ips_sorted || $existing_cdn !== $cdn_sorted;

                $update = $this->db->prepare(
                    'UPDATE live_subdomains
                     SET scope = :scope,
                         ips = :ips::jsonb,
                         cdn = :cdn::jsonb,
                         last_update = :last_update
                     WHERE subdomain = :subdomain AND program_name = :program_name'
                );
                $update->execute([
                    ':scope' => $scope,
                    ':ips' => json_encode($ips_sorted),
                    ':cdn' => json_encode($cdn_sorted),
                    ':last_update' => $now,
                    ':subdomain' => $subdomain,
                    ':program_name' => $program_name,
                ]);

                if ($changesDetected) {
                    $this->logChange(sprintf('Updated live subdomain: %s', $subdomain));
                }

                return true;
            }

            $insert = $this->db->prepare(
                'INSERT INTO live_subdomains (program_name, subdomain, scope, ips, cdn, created_date, last_update)
                 VALUES (:program_name, :subdomain, :scope, :ips::jsonb, :cdn::jsonb, :created_date, :last_update)'
            );
            $insert->execute([
                ':program_name' => $program_name,
                ':subdomain' => $subdomain,
                ':scope' => $scope,
                ':ips' => json_encode($ips_sorted),
                ':cdn' => json_encode($cdn_sorted),
                ':created_date' => $now,
                ':last_update' => $now,
            ]);

            $this->logChange(sprintf('Inserted new live subdomain: %s', $subdomain));

            if ($this->messenger) {
                $this->messenger->send(
                    sprintf(
                        "```%s (fresh live) has been added to '%s'```",
                        $subdomain,
                        $program_name
                    )
                );
            }

            return true;
        } catch (PDOException $exception) {
            error_log('LiveSubdomain::upsert_lives PDOException: ' . $exception->getMessage());
            return false;
        } catch (\Throwable $throwable) {
            error_log('LiveSubdomain::upsert_lives error: ' . $throwable->getMessage());
            return false;
        }
    }

    public function getAllLives(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM live_subdomains ORDER BY last_update DESC, created_date DESC'
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLivesByProgram(string $program_name): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM live_subdomains WHERE program_name = :program_name ORDER BY last_update DESC, created_date DESC'
        );
        $stmt->execute([':program_name' => trim($program_name)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function extractScope(string $subdomain): string
    {
        $parts = explode('.', $subdomain);
        return count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $subdomain;
    }

    private function normalizeAndSort(array $values): array
    {
        $filtered = array_values(array_filter($values, static function ($value): bool {
            return is_string($value) && $value !== '';
        }));
        sort($filtered);

        return $filtered;
    }

    private function logChange(string $message): void
    {
        $timestamp = $this->helper::current_time();
        echo sprintf("[+] [%s] %s%s%s\n", $timestamp, "\033[0;32m", $message, "\033[0m");
    }
}
