<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Helpers;
use App\Tools\SendDiscordMessage;
use PDO;
use PDOException;

final class Port
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

    public function upsertPort(
        string $programName,
        string $subdomain,
        string $host,
        int $port,
        string $protocol,
        ?string $service,
        string $source,
        array $metadata = []
    ): bool {
        $programName = trim($programName);
        $subdomain = strtolower(trim($subdomain));
        $host = trim($host !== '' ? $host : $subdomain);
        $protocol = strtolower(trim($protocol));
        $service = $service !== null ? trim($service) : '';
        $source = trim($source);

        if ($programName === '' || $subdomain === '' || $port <= 0 || $protocol === '') {
            return false;
        }

        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        if ($metadataJson === false) {
            $metadataJson = json_encode(['raw' => $metadata]);
        }

        $now = Helpers::current_time();

        try {
            $stmt = $this->db->prepare(
                'SELECT id, service, metadata FROM ports
                 WHERE program_name = :program_name
                   AND subdomain = :subdomain
                   AND host = :host
                   AND port = :port
                   AND protocol = :protocol
                 LIMIT 1'
            );
            $stmt->execute([
                ':program_name' => $programName,
                ':subdomain' => $subdomain,
                ':host' => $host,
                ':port' => $port,
                ':protocol' => $protocol,
            ]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updates = $this->db->prepare(
                    'UPDATE ports
                     SET service = :service,
                         source = :source,
                         metadata = :metadata::jsonb,
                         last_update = :last_update
                     WHERE id = :id'
                );
                $updates->execute([
                    ':service' => $service,
                    ':source' => $source,
                    ':metadata' => $metadataJson,
                    ':last_update' => $now,
                    ':id' => $existing['id'],
                ]);

                if ($this->messenger && ($existing['service'] ?? '') !== $service) {
                    $this->messenger->send(sprintf(
                        "```%s:%d (%s) service changed from '%s' to '%s'```",
                        $subdomain,
                        $port,
                        $protocol,
                        $existing['service'] ?? 'unknown',
                        $service !== '' ? $service : 'unknown'
                    ));
                }

                return true;
            }

            $insert = $this->db->prepare(
                'INSERT INTO ports (
                    program_name, subdomain, host, port, protocol, service, source, metadata, created_date, last_update
                 ) VALUES (
                    :program_name, :subdomain, :host, :port, :protocol, :service, :source, :metadata::jsonb, :created_date, :last_update
                 )'
            );
            $insert->execute([
                ':program_name' => $programName,
                ':subdomain' => $subdomain,
                ':host' => $host,
                ':port' => $port,
                ':protocol' => $protocol,
                ':service' => $service,
                ':source' => $source,
                ':metadata' => $metadataJson,
                ':created_date' => $now,
                ':last_update' => $now,
            ]);

            if ($this->messenger) {
                $this->messenger->send(sprintf(
                    "```Found open %s/%d on %s (%s)```",
                    $protocol,
                    $port,
                    $subdomain,
                    $programName
                ));
            }

            return true;
        } catch (PDOException $exception) {
            error_log('Port::upsertPort error: ' . $exception->getMessage());
            return false;
        }
    }

    public function getPortsByProgram(string $programName): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ports WHERE program_name = :program_name ORDER BY last_update DESC, port ASC'
        );
        $stmt->execute([':program_name' => trim($programName)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllPorts(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ports ORDER BY last_update DESC, port ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

