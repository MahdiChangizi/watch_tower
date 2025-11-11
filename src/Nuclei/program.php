#!/usr/bin/env php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Tools\Nuclei;

// Check if domain list file is provided
if ($argc < 2) {
    echo "Usage: php src/Nuclei/program.php <domain.list>\n";
    echo "Example: php src/Nuclei/program.php domain.list\n";
    exit(1);
}

$domainFile = $argv[1];

// Check if file exists
if (!file_exists($domainFile)) {
    echo "Error: File '$domainFile' not found.\n";
    exit(1);
}

// Check if file is readable
if (!is_readable($domainFile)) {
    echo "Error: File '$domainFile' is not readable.\n";
    exit(1);
}

// Run Nuclei
echo "\033[36m[+] Running Nuclei on domain list: $domainFile\033[0m\n";
$nuclei = new Nuclei($domainFile);
$results = $nuclei->run();

// Display results
if (!empty($results)) {
    echo "\n\033[32m[+] Found " . count($results) . " result(s):\033[0m\n";
    foreach ($results as $result) {
        echo "$result\n";
    }
} else {
    echo "\033[33m[!] No results found.\033[0m\n";
}

echo "\n\033[36m[+] Nuclei scan completed.\033[0m\n";

