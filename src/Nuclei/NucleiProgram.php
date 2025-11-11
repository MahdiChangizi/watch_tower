<?php
namespace App\Nuclei;

use App\Tools\Nuclei;
// use App\Tools\SendDiscordMessage;

class NucleiProgram
{
    
    // private SendDiscordMessage $discord;

    public function __construct() {
        // $this->discord = new SendDiscordMessage();
    }

    # get program name and get all subdomains from database
    # run nuclei with subdomains
    # then if find something send a message to discord

    public function run_nuclei_on_program(string $programName, array $subdomains) {
        // Validate input
        if (empty($subdomains)) {
            echo "No subdomains provided for program: $programName\n";
            return [];
        }

        // Filter and clean subdomains
        $subdomainList = [];
        foreach ($subdomains as $subdomain) {
            // Handle both string arrays and database result arrays
            if (is_array($subdomain)) {
                $line = trim($subdomain['subdomain'] ?? '');
            } else {
                $line = trim($subdomain);
            }
            if ($line !== '') {
                $subdomainList[] = $line;
            }
        }

        if (empty($subdomainList)) {
            echo "No valid subdomains found for program: $programName\n";
            return [];
        }

        // Create temporary file
        $rand = rand();
        $tmpFile = '/tmp/nuclei_domains_' . $rand . '.txt';
        
        // Write subdomains to file (with trailing newline like other tools)
        $written = file_put_contents($tmpFile, implode("\n", $subdomainList) . "\n");
        
        if ($written === false) {
            echo "Error: Failed to write temporary file: $tmpFile\n";
            return [];
        }

        // Run Nuclei
        try {
            $nuclei = new Nuclei($tmpFile);
            $results = $nuclei->run();
        } finally {
            // Clean up temporary file
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        return $results;
    }

}
