<?php

namespace App\Tools;

class Crtsh {
    private array $subdomains = [];

    public function __construct(string $input) {
        // Read domains from file
        if (!file_exists($input)) {
            echo "\033[31m[!] File not found: {$input}\033[0m\n";
            return;
        }

        $domains = file($input, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (empty($domains)) {
            echo "\033[31m[!] No domains found in file: {$input}\033[0m\n";
            return;
        }

        $allSubdomains = [];

        // Process each domain
        foreach ($domains as $domain) {
            $domain = trim($domain);
            if (empty($domain)) {
                continue;
            }

            // Run crtsh through an interactive zsh so zsh aliases/functions and zshrc are loaded
            $shellCommand = "crtsh " . escapeshellarg($domain);
            $command = "zsh -ic " . escapeshellarg($shellCommand);

            echo "\033[90m[+] Running command: {$command}\033[0m\n";

            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                // optional: show a warning but continue
                echo "\033[33m[!] Command returned non-zero ({$returnVar}) for domain: {$domain}\033[0m\n";
            }

            if (!empty($output)) {
                $allSubdomains = array_merge($allSubdomains, $output);
            }
        }

        // Remove duplicates and empty values
        $this->subdomains = array_values(array_filter(array_unique($allSubdomains)));
    }

    public function getSubdomains(): array {
        return $this->subdomains;
    }
}
