<?php

require_once __DIR__ . '/../db/db.php';
use App\Services\Program;

global $db;
$db = Database::connect();
Database::createTables();

$programService = new Program();

# pahth to programs directory
$programsPath = __DIR__ . '/../programs/';


# get all JSON files in the directory
$jsonFiles = glob($programsPath . '*.json');

foreach ($jsonFiles as $file) {
    if (!is_file($file)) continue; # safety check

    $jsonContent = file_get_contents($file);
    if (!$jsonContent) {
        error_log("Failed to read file: $file");
        continue;
    }

    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON in file $file: " . json_last_error_msg());
        continue;
    }

    // Check that required keys exist
    if (!isset($data['program_name'], $data['scopes'], $data['ooscopes'], $data['config'])) {
        error_log("Missing required keys in file $file");
        continue;
    }

    // Create or update program
    $programService->upsert_program(
        $data['program_name'],
        $data['scopes'],
        $data['ooscopes'],
        $data['config']
    );
}

echo "\033[36m" . "--------------------------------------------------------\n";

echo "\n" . "\033[36m" . "
  ██████╗██╗  ██╗ █████╗ ███╗   ██╗ ██████╗ ██╗███████╗
 ██╔════╝██║  ██║██╔══██╗████╗  ██║██╔════╝ ██║╚══███╔╝
 ██║     ███████║███████║██╔██╗ ██║██║  ███╗██║  ███╔╝ 
 ██║     ██╔══██║██╔══██║██║╚██╗██║██║   ██║██║ ███╔╝  
 ╚██████╗██║  ██║██║  ██║██║ ╚████║╚██████╔╝██║███████╗
  ╚═════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝  ╚═══╝ ╚═════╝ ╚═╝╚══════╝
" . "\n";

echo "\033[36m" . "--------------------------------------------------------\n\n";
echo "\033[35m" . "[+] Program synchronization completed.\n" . "\33[0m";