<?php
declare(strict_types=1);

namespace App\Ports;

use App\Helpers\Helpers;
use App\Services\LiveSubdomain;
use App\Services\Port as PortService;
use App\Tools\Naabu;

final class PortScanner
{
    private PortService $ports;
    private Helpers $helper;
    private LiveSubdomain $liveSubdomains;

    public function __construct(
        ?PortService $ports = null,
        ?Helpers $helper = null,
        ?LiveSubdomain $liveSubdomains = null
    ) {
        $this->ports = $ports ?? new PortService();
        $this->helper = $helper ?? new Helpers();
        $this->liveSubdomains = $liveSubdomains ?? new LiveSubdomain();
    }

    public function run(): void
    {
        $live = $this->liveSubdomains->getAllLives();
        if (!$live) {
            echo "[!] No live subdomains available for port scanning.\n";
            return;
        }

        $targets = [];
        $subdomainMap = [];

        foreach ($live as $record) {
            $subdomain = strtolower(trim((string) ($record['subdomain'] ?? '')));
            if ($subdomain === '') {
                continue;
            }

            $targets[] = $subdomain;
            $subdomainMap[$subdomain] = $record;
        }

        $targets = array_values(array_unique($targets));
        if (!$targets) {
            echo "[!] No valid targets for port scanning.\n";
            return;
        }

        $tempFile = $this->helper->create_temp_file($targets, 'ports');

        try {
            $naabu = new Naabu($tempFile);
            $results = $naabu->getResults();
        } finally {
            $this->helper->delete_temp_file($tempFile);
        }

        if (!$results) {
            echo "[!] Naabu returned no open ports.\n";
            return;
        }

        foreach ($results as $result) {
            $input = strtolower(trim((string) ($result['input'] ?? $result['host'] ?? '')));
            if ($input === '' || !isset($subdomainMap[$input])) {
                continue;
            }

            $record = $subdomainMap[$input];

            $programName = (string) ($record['program_name'] ?? '');
            $host = (string) ($result['host'] ?? $record['subdomain']);
            $port = (int) ($result['port'] ?? 0);
            $protocol = (string) ($result['protocol'] ?? 'tcp');

            if ($port <= 0) {
                continue;
            }

            $service = '';
            if (isset($result['service'])) {
                if (is_array($result['service'])) {
                    $service = (string) ($result['service']['name'] ?? $result['service']['product'] ?? '');
                } else {
                    $service = (string) $result['service'];
                }
            }

            $metadata = [
                'cpe' => $result['cpe'] ?? null,
                'service' => $result['service'] ?? null,
                'timestamp' => $result['timestamp'] ?? null,
                'ip' => $result['ip'] ?? ($record['ips'] ?? null),
            ];

            $this->ports->upsertPort(
                $programName,
                $record['subdomain'],
                $host,
                $port,
                $protocol,
                $service,
                'naabu',
                array_filter($metadata, static fn($value) => $value !== null)
            );
        }
    }
}

