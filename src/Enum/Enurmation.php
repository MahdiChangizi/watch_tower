<?php
namespace App\Enum;
require_once __DIR__ . '/../../public/index.php';

use App\Services\Program;
use App\Tools\Subfinder;
use App\Services\Subdomain;

class Enurmation {
    public $program = null;
    public $subdomain = null;
    const GREAN = "\033[0;32m";

    public function __construct() {
        $this->program = new Program();
        $this->subdomain = new Subdomain();
    }

    public function enurmation_all_programs(): void {
        $programs = $this->program->get_all_programs();

        foreach ($programs as $program) {
            $programName = $program['program_name'];
            echo "[+] Program: " . self::GREAN . $programName . "\n";

            $scopes = $program['scopes'];
            if (is_string($scopes)) {
                $scopes = json_decode($scopes, true);
            }

            if (empty($scopes) || !is_array($scopes)) {
                echo "    [!] No scopes defined for program: {$programName}\n";
                continue;
            }

            $scopes = array_filter(array_map('trim', $scopes), fn($value) => $value !== '');
            if (empty($scopes)) {
                echo "    [!] Scopes list is empty after filtering for program: {$programName}\n";
                continue;
            }

            $rand = rand();
            $tmpFile = '/tmp/domains_' . $programName . '_' . $rand . '.txt';
            file_put_contents($tmpFile, implode("\n", $scopes) . "\n");

            try {
                $sources = [
                    'subfinder' => new Subfinder($tmpFile, true),
                    'chaos' => new \App\Tools\Chaos($tmpFile),
                    'crtsh' => new \App\Tools\Crtsh($tmpFile),
                    'samoscout' => new \App\Tools\Samoscout($tmpFile),
                ];

                foreach ($sources as $provider => $tool) {
                    $subdomains = $tool->getSubdomains();
                    if (empty($subdomains)) {
                        continue;
                    }

                    $subdomains = array_unique(array_map(function ($value) {
                        return strtolower(trim($value));
                    }, $subdomains));

                    foreach ($subdomains as $subdomain) {
                        if ($subdomain === '') {
                            continue;
                        }
                        $this->subdomain->upsert_subdomain($programName, $subdomain, $provider);
                    }
                }
            } finally {
                if (file_exists($tmpFile)) {
                    unlink($tmpFile);
                }
            }
        }
    }

}
