<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Helpers;
use App\Tools\SendDiscordMessage;
use PDO;
use PDOException;

final class Subdomain
{
    private PDO $db;
    private Helpers $helper;
    private ?SendDiscordMessage $messenger = null;

    public function __construct(?PDO $db = null, ?Helpers $helper = null, ?SendDiscordMessage $messenger = null)
    {
        $this->db = $db ?? \Database::connect();
        $this->helper = $helper ?? new Helpers();
        $this->messenger = $messenger ?? SendDiscordMessage::createOrNull();
    }

    public function upsert_subdomain(string $program_name, string $subdomain_name, string $provider): bool
    {
        $program_name = trim($program_name);
        $subdomain_name = strtolower(trim($subdomain_name));
        $provider = trim($provider);

        if ($program_name === '' || $subdomain_name === '') {
            return false;
        }

        $now = Helpers::current_time();
        $scope = $program_name;

        try {
            $stmt = $this->db->prepare(
                'SELECT id, provider FROM subdomains WHERE program_name = :program_name AND subdomain = :subdomain LIMIT 1'
            );
            $stmt->execute([
                ':program_name' => $program_name,
                ':subdomain' => $subdomain_name,
            ]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($provider !== '' && ($existing['provider'] ?? '') !== $provider) {
                    $update = $this->db->prepare(
                        'UPDATE subdomains
                         SET provider = :provider, last_update = :last_update
                         WHERE id = :id'
                    );
                    $update->execute([
                        ':provider' => $provider,
                        ':last_update' => $now,
                        ':id' => $existing['id'],
                    ]);
                }

                return true;
            }

            $insert = $this->db->prepare(
                'INSERT INTO subdomains (program_name, subdomain, provider, scope)
                 VALUES (:program_name, :subdomain, :provider, :scope)'
            );
            $insert->execute([
                ':program_name' => $program_name,
                ':subdomain' => $subdomain_name,
                ':provider' => $provider,
                ':scope' => $scope,
            ]);

            if ($this->messenger) {
                // $this->messenger->send(
                //     sprintf(
                //         "```%s (new subdomain) added to %s```",
                //         $subdomain_name,
                //         $program_name
                //     )
                // );
            }

            error_log(sprintf(
                '[%s] New subdomain inserted: %s (%s)',
                $now,
                $subdomain_name,
                $program_name
            ));

            return true;
        } catch (PDOException $exception) {
            error_log('Subdomain::upsert_subdomain error: ' . $exception->getMessage());
            return false;
        }
    }

    public function get_subdomains_by_program(string $program_name): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subdomains WHERE program_name = :program_name ORDER BY last_update DESC, created_date DESC'
        );
        $stmt->execute([':program_name' => trim($program_name)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
