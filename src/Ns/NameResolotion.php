<?php
declare(strict_types=1);

namespace App\Ns;

use App\Helpers\Helpers;
use App\Services\LiveSubdomain;
use App\Services\Program;
use App\Services\Subdomain;
use App\Tools\Dnsx;

final class NameResolotion
{
    private const GREEN = "\033[0;32m";
    private const RESET = "\033[0m";

    private Program $programService;
    private Subdomain $subdomainService;
    private LiveSubdomain $liveService;
    private Helpers $helper;

    public function __construct(
        ?Program $programService = null,
        ?Subdomain $subdomainService = null,
        ?LiveSubdomain $liveService = null,
        ?Helpers $helper = null
    ) {
        $this->programService = $programService ?? new Program();
        $this->subdomainService = $subdomainService ?? new Subdomain();
        $this->liveService = $liveService ?? new LiveSubdomain();
        $this->helper = $helper ?? new Helpers();
    }

    public function name_resolotion_all_programs(): void
    {
        foreach ($this->programService->get_all_programs() as $program) {
            $programName = (string) ($program['program_name'] ?? '');
            if ($programName === '') {
                continue;
            }

            echo sprintf("[+] Program: %s%s%s\n", self::GREEN, $programName, self::RESET);

            $subdomains = $this->subdomainService->get_subdomains_by_program($programName);
            $subdomainList = $this->extractSubdomains($subdomains);

            if (!$subdomainList) {
                echo sprintf("    [!] No valid subdomains found for program: %s\n", $programName);
                continue;
            }

            $tmpFile = $this->helper->create_temp_file($subdomainList, 'dnsx');

            try {
                $dnsx = new Dnsx($tmpFile);
                $resolvedDomains = $dnsx->getResolvedDomains();

                if (!$resolvedDomains) {
                    echo sprintf("    [!] No resolved domains returned by dnsx for program: %s\n", $programName);
                    continue;
                }

                foreach ($resolvedDomains as $dnsData) {
                    $host = $dnsData['host'] ?? null;
                    if (!$host) {
                        continue;
                    }

                    $ips = $dnsData['ips'] ?? [];
                    $cnames = $dnsData['cnames'] ?? [];

                    if (!$ips && !$cnames) {
                        continue;
                    }

                    $this->liveService->upsert_lives(
                        $programName,
                        $host,
                        is_array($ips) ? $ips : [],
                        is_array($cnames) ? $cnames : []
                    );
                }
            } finally {
                $this->helper->delete_temp_file($tmpFile);
            }
        }
    }

    private function extractSubdomains(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $value = strtolower(trim((string) ($row['subdomain'] ?? '')));
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return array_values(array_unique($result));
    }
}