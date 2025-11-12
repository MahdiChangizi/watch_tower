<?php
namespace App\Ns;
require_once __DIR__ . '/../../public/index.php';

use App\Services\Program;
use App\Services\Subdomain;
use App\Tools\Dnsx;
use App\Services\LiveSubdomain;

class NameResolotion {
    // Name resolution for all subdomains for all programs with dnsx
    public function name_resolotion_all_programs(): void {
        $programService = new Program();
        $subdomainService = new Subdomain();
        $liveSubdomainService = new LiveSubdomain();

        $programs = $programService->get_all_programs();

        foreach ($programs as $program) {
            echo "[+] Program: " . "\033[0;32m" . $program['program_name'] . "\033[0m" . "\n";

            // get all subdomains for the program
            $subdomains = $subdomainService->get_subdomains_by_program($program['program_name']);

            $subdomainList = [];
            foreach ($subdomains as $subdomain) {
                $line = trim($subdomain['subdomain']);
                if ($line !== '') {
                    $subdomainList[] = $line;
                }
            }

            if (empty($subdomainList)) {
                echo "No valid subdomains found for program: " . $program['program_name'] . "\n";
                continue;
            }

            // create temporary file for dnsx input
            $rand = rand();
            $tmpFile = '/tmp/subdomains_' . $rand . '.txt';
            file_put_contents($tmpFile, implode("\n", $subdomainList) . "\n");

            // run dnsx
            $dnsx = new Dnsx($tmpFile);
            $resolvedDomains = $dnsx->getResolvedDomains();

            if (empty($resolvedDomains)) {
                echo "No resolved domains returned by dnsx for program: " . $program['program_name'] . "\n";
                unlink($tmpFile);
                continue;
            }

            foreach ($resolvedDomains as $dnsData) {
                $host = $dnsData['host'] ?? null;
                $ips = $dnsData['ips'] ?? [];
                $cnames = $dnsData['cnames'] ?? [];

                if (!$host) {
                    // echo "Skipping empty host entry.\n";
                    continue;
                }

                if (empty($ips) && empty($cnames)) {
                    // echo "No DNS resolution for: {$host}\n";
                    continue;
                }

                // upsert resolved domain into database
                $liveSubdomainService->upsert_lives(
                    $program['program_name'],
                    $host,
                    $ips,
                    $cnames
                );

                
            }

            // remove temporary file
            unlink($tmpFile);
        }
    }
}

