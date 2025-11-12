<?php
declare(strict_types=1);

namespace App\Enum;

use App\Helpers\Helpers;
use App\Services\Program;
use App\Services\Subdomain;
use App\Tools\Chaos;
use App\Tools\Crtsh;
use App\Tools\Samoscout;
use App\Tools\Subfinder;

final class Enurmation
{
    private const GREEN = "\033[0;32m";
    private const RESET = "\033[0m";

    private Program $programService;
    private Subdomain $subdomainService;
    private Helpers $helper;

    public function __construct(?Program $programService = null, ?Subdomain $subdomainService = null, ?Helpers $helper = null)
    {
        $this->programService = $programService ?? new Program();
        $this->subdomainService = $subdomainService ?? new Subdomain();
        $this->helper = $helper ?? new Helpers();
    }

    public function enurmation_all_programs(): void
    {
        $programs = $this->programService->get_all_programs();

        foreach ($programs as $program) {
            $programName = (string) ($program['program_name'] ?? '');
            if ($programName === '') {
                continue;
            }

            echo sprintf("[+] Program: %s%s%s\n", self::GREEN, $programName, self::RESET);

            $scopes = $this->normalizeScopes($program['scopes'] ?? []);
            if (!$scopes) {
                echo sprintf("    [!] No valid scopes for program: %s\n", $programName);
                continue;
            }

            $tmpFile = $this->helper->create_temp_file($scopes, $this->sanitizeFileName($programName));

            try {
                $sources = [
                    'subfinder' => new Subfinder($tmpFile, true),
                    'chaos' => new Chaos($tmpFile),
                    'crtsh' => new Crtsh($tmpFile),
                    'samoscout' => new Samoscout($tmpFile),
                ];

                foreach ($sources as $provider => $tool) {
                    $this->ingestSubdomains($programName, $tool->getSubdomains(), $provider);
                }
            } finally {
                $this->helper->delete_temp_file($tmpFile);
            }
        }
    }

    private function normalizeScopes($scopes): array
    {
        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $scopes = $decoded;
            }
        }

        if (!is_array($scopes)) {
            return [];
        }

        $filtered = array_map(static function ($value) {
            return strtolower(trim((string) $value));
        }, $scopes);

        $filtered = array_filter($filtered, static fn($value) => $value !== '');

        return array_values(array_unique($filtered));
    }

    private function ingestSubdomains(string $programName, array $subdomains, string $provider): void
    {
        if (!$subdomains) {
            return;
        }

        foreach ($subdomains as $subdomain) {
            $normalized = strtolower(trim((string) $subdomain));
            if ($normalized === '') {
                continue;
            }

            $this->subdomainService->upsert_subdomain($programName, $normalized, $provider);
        }
    }

    private function sanitizeFileName(string $value): string
    {
        return preg_replace('/[^a-z0-9_\-]+/i', '_', $value) ?? 'program';
    }
}
