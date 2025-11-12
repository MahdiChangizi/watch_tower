#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Nuclei\NucleiProgram;
use App\Services\LiveSubdomain;

if ($argc < 2) {
    echo "Usage: php src/Nuclei/program.php <program_name>\n";
    echo "Example: php src/Nuclei/program.php discourse\n";
    exit(1);
}

$programName = trim($argv[1]);
if ($programName === '') {
    echo "Error: Program name cannot be empty.\n";
    exit(1);
}

global $db;
$db = Database::connect();

$liveService = new LiveSubdomain();
$liveSubdomains = $liveService->getLivesByProgram($programName);

if (empty($liveSubdomains)) {
    echo "\033[33m[!] No live subdomains found for program: {$programName}\033[0m\n";
    exit(0);
}

$nucleiProgram = new NucleiProgram();
$results = $nucleiProgram->run_nuclei_on_program($programName, $liveSubdomains);

if (!empty($results)) {
    echo "\n\033[32m[+] Found " . count($results) . " result(s):\033[0m\n";
    foreach ($results as $result) {
        echo "{$result}\n";
    }
} else {
    echo "\033[33m[!] No results found by nuclei.\033[0m\n";
}

echo "\n\033[36m[+] Nuclei scan completed for program '{$programName}'.\033[0m\n";