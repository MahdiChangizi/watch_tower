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

        $allScopes = [];

        foreach ($programs as $program) {
            echo "[+] Program: " . self::GREAN . $program['program_name'] . "\n";
            $program['scopes'] = json_decode($program['scopes'], true);
            foreach ($program['scopes'] as $scope) {
                $allScopes[] = $scope;
            }
        }

        $rand = rand();
        $tmpFile = '/tmp/domains_' . $rand . '.txt';
        file_put_contents($tmpFile, implode("\n", $allScopes));

        
        # Tools
        $subfinder = new Subfinder($tmpFile, true); 
        
        
        foreach ($subfinder->getSubdomains() as $subdomain) {
            // save all domain in database
            $this->subdomain->upsert_subdomain($program['program_name'], $subdomain, 'subfinder');
        }
    }

}
