<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Helpers;
use PDO;
use PDOException;

final class UrlFinding
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * @param array<int, string> $parameters
     *
     * @return 'inserted'|'updated'
     */
    public function upsert(
        string $program_name,
        string $scope,
        string $url,
        array $parameters,
        string $source = 'waybackurls'
    ): string {
        $program_name = trim($program_name);
        $scope = trim($scope);
        $url = trim($url);
        $source = trim($source) !== '' ? trim($source) : 'waybackurls';

        if ($program_name === '' || $url === '') {
            throw new \InvalidArgumentException('Program name and URL cannot be empty.');
        }

        $normalizedParameters = $this->normalizeParameters($parameters);
        $now = Helpers::current_time();

        try {
            $stmt = $this->db->prepare(
                'SELECT id, parameters, occurrences FROM urls WHERE program_name = :program_name AND url = :url LIMIT 1'
            );
            $stmt->execute([
                ':program_name' => $program_name,
                ':url' => $url,
            ]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $existingParams = $this->normalizeParameters(
                    json_decode((string) ($existing['parameters'] ?? '[]'), true) ?: []
                );

                $mergedParams = array_values(array_unique(array_merge($existingParams, $normalizedParameters)));
                $occurrences = max(1, (int) ($existing['occurrences'] ?? 1)) + 1;

                $update = $this->db->prepare(
                    'UPDATE urls
                     SET scope = :scope,
                         parameters = :parameters::jsonb,
                         source = :source,
                         occurrences = :occurrences,
                         last_seen = :last_seen
                     WHERE id = :id'
                );

                $update->execute([
                    ':scope' => $scope !== '' ? $scope : null,
                    ':parameters' => json_encode($mergedParams, JSON_UNESCAPED_UNICODE),
                    ':source' => $source,
                    ':occurrences' => $occurrences,
                    ':last_seen' => $now,
                    ':id' => $existing['id'],
                ]);

                return 'updated';
            }

            $insert = $this->db->prepare(
                'INSERT INTO urls (
                    program_name,
                    scope,
                    url,
                    parameters,
                    source,
                    occurrences,
                    first_seen,
                    last_seen
                ) VALUES (
                    :program_name,
                    :scope,
                    :url,
                    :parameters::jsonb,
                    :source,
                    :occurrences,
                    :first_seen,
                    :last_seen
                )'
            );

            $insert->execute([
                ':program_name' => $program_name,
                ':scope' => $scope !== '' ? $scope : null,
                ':url' => $url,
                ':parameters' => json_encode($normalizedParameters, JSON_UNESCAPED_UNICODE),
                ':source' => $source,
                ':occurrences' => 1,
                ':first_seen' => $now,
                ':last_seen' => $now,
            ]);

            return 'inserted';
        } catch (PDOException $exception) {
            error_log('UrlFinding::upsert PDOException: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * @param array<int, array{url: string, scope?: string, parameters?: array<int, string>}> $entries
     */
    public function bulkUpsert(string $program_name, array $entries, string $source = 'waybackurls'): array
    {
        $summary = [
            'inserted' => 0,
            'updated' => 0,
        ];

        foreach ($entries as $entry) {
            if (!isset($entry['url'])) {
                continue;
            }

            $scope = isset($entry['scope']) ? (string) $entry['scope'] : '';
            $parameters = isset($entry['parameters']) && is_array($entry['parameters'])
                ? $entry['parameters']
                : [];

            $result = $this->upsert(
                $program_name,
                $scope,
                (string) $entry['url'],
                $parameters,
                $source
            );

            if ($result === 'inserted') {
                $summary['inserted']++;
            } elseif ($result === 'updated') {
                $summary['updated']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<int, mixed> $parameters
     *
     * @return array<int, string>
     */
    private function normalizeParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $parameter) {
            if (!is_string($parameter)) {
                continue;
            }

            $parameter = strtolower(trim($parameter));
            if ($parameter === '') {
                continue;
            }

            $normalized[$parameter] = $parameter;
        }

        return array_values($normalized);
    }
}

