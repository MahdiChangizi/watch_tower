<?php
declare(strict_types=1);

namespace App\Urls;

use App\Services\UrlFinding;
use App\Tools\WaybackUrls;
use PDO;
use PDOException;

final class UrlScanner
{
    private PDO $db;
    private UrlFinding $urlFinding;

    public function __construct(?PDO $db = null, ?UrlFinding $urlFinding = null)
    {
        $this->db = $db ?? \Database::connect();
        $this->urlFinding = $urlFinding ?? new UrlFinding($this->db);
    }

    /**
     * @param array{
     *     paramsFile?: string|null,
     *     save?: bool,
     *     limit?: int|null,
     *     flags?: array<int, string>,
     *     source?: string
     * } $options
     */
    public function scan(string $programName, array $options = []): array
    {
        $programName = trim($programName);
        if ($programName === '') {
            throw new \InvalidArgumentException('Program name cannot be empty.');
        }

        $program = $this->fetchProgram($programName);
        $scopes = $program['scopes'];

        if (empty($scopes)) {
            return [
                'program' => $programName,
                'scopes' => [],
                'parameters_source' => null,
                'parameters_loaded' => 0,
                'total_urls' => 0,
                'unique_urls' => 0,
                'match_count' => 0,
                'matches' => [],
                'persisted' => null,
                'errors' => [],
            ];
        }

        $parameterFile = $this->resolveParameterFile($options['paramsFile'] ?? null);
        $parameters = $this->loadParameters($parameterFile);
        if (empty($parameters)) {
            throw new \RuntimeException(sprintf('No parameters loaded from %s.', $parameterFile));
        }

        $parameterSet = array_fill_keys($parameters, true);
        $flags = isset($options['flags']) && is_array($options['flags']) ? $options['flags'] : [];
        $limit = isset($options['limit']) ? (int) $options['limit'] : null;
        $source = isset($options['source']) && is_string($options['source']) && trim($options['source']) !== ''
            ? trim($options['source'])
            : 'waybackurls';

        $allUrls = [];
        $uniqueAccumulator = [];
        $matches = [];
        $errors = [];

        foreach ($scopes as $scope) {
            $scope = trim($scope);
            if ($scope === '') {
                continue;
            }

            try {
                $wayback = new WaybackUrls($scope);
                $urls = $wayback->fetch($flags, true);

                if ($limit !== null && $limit > 0) {
                    $urls = array_slice($urls, 0, $limit);
                }

                $allUrls[$scope] = $urls;

                foreach ($urls as $url) {
                    $normalizedUrl = $this->normalizeUrl($url);
                    if ($normalizedUrl === '') {
                        continue;
                    }

                    $uniqueAccumulator[$normalizedUrl] = true;
                    $matchedParameters = $this->detectParameters($normalizedUrl, $parameterSet);
                    if (empty($matchedParameters)) {
                        continue;
                    }

                    if (!isset($matches[$normalizedUrl])) {
                        $matches[$normalizedUrl] = [
                            'url' => $normalizedUrl,
                            'scope' => $scope,
                            'parameters' => $matchedParameters,
                        ];
                    } else {
                        $existingParams = $matches[$normalizedUrl]['parameters'];
                        $merged = array_values(array_unique(array_merge($existingParams, $matchedParameters)));
                        $matches[$normalizedUrl]['parameters'] = $merged;
                    }
                }
            } catch (\Throwable $throwable) {
                $errors[] = [
                    'scope' => $scope,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        $matchList = array_values($matches);
        $persisted = null;

        if (!empty($options['save']) && !empty($matchList)) {
            $persisted = $this->urlFinding->bulkUpsert($programName, $matchList, $source);
        }

        return [
            'program' => $programName,
            'scopes' => array_values($scopes),
            'parameters_source' => $parameterFile,
            'parameters_loaded' => count($parameters),
            'total_urls' => array_sum(array_map('count', $allUrls)),
            'unique_urls' => count($uniqueAccumulator),
            'match_count' => count($matchList),
            'matches' => $matchList,
            'persisted' => $persisted,
            'errors' => $errors,
        ];
    }

    private function fetchProgram(string $programName): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT program_name, scopes, ooscopes, config FROM programs WHERE program_name = :program_name LIMIT 1'
            );
            $stmt->execute([':program_name' => $programName]);
            $program = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            throw new \RuntimeException('Database error while fetching program: ' . $exception->getMessage(), 0, $exception);
        }

        if (!$program) {
            throw new \RuntimeException(sprintf("Program '%s' was not found in the database.", $programName));
        }

        return [
            'program_name' => $program['program_name'],
            'scopes' => $this->decodeJsonArray($program['scopes'] ?? '[]'),
            'ooscopes' => $this->decodeJsonArray($program['ooscopes'] ?? '[]'),
            'config' => $this->decodeJsonArray($program['config'] ?? '{}'),
        ];
    }

    private function resolveParameterFile(?string $customPath): string
    {
        if ($customPath !== null) {
            $customPath = trim($customPath);
            if ($customPath === '') {
                throw new \InvalidArgumentException('Parameter file path cannot be empty.');
            }
            if (!is_file($customPath)) {
                throw new \RuntimeException(sprintf('Parameter file not found: %s', $customPath));
            }

            return $customPath;
        }

        $defaultPath = dirname(__DIR__, 2) . '/common_param.txt';
        if (!is_file($defaultPath)) {
            throw new \RuntimeException('Default parameter file missing at ' . $defaultPath);
        }

        return $defaultPath;
    }

    /**
     * @return array<int, string>
     */
    private function loadParameters(string $path): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException(sprintf('Unable to read parameter file: %s', $path));
        }

        $parameters = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $line = strtolower($line);
            $parameters[$line] = $line;
        }

        return array_values($parameters);
    }

    /**
     * @param array<string, bool> $parameterSet
     *
     * @return array<int, string>
     */
    private function detectParameters(string $url, array $parameterSet): array
    {
        $found = [];
        $parsed = parse_url($url);

        if (isset($parsed['query'])) {
            $queryParameters = [];
            parse_str($parsed['query'], $queryParameters);

            foreach (array_keys($queryParameters) as $key) {
                $key = strtolower((string) $key);
                if (isset($parameterSet[$key])) {
                    $found[$key] = $key;
                }
            }
        }

        if (!$found) {
            foreach (array_keys($parameterSet) as $param) {
                if (stripos($url, $param . '=') !== false) {
                    $found[$param] = $param;
                }
            }
        }

        return array_values($found);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $url;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeJsonArray(string $json): array
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}

