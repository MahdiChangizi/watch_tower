<?php
declare(strict_types=1);

require_once __DIR__ . '/../db/db.php';

use App\Services\Program;

try {
    Database::connect();
    Database::createTables();
} catch (\Throwable $exception) {
    error_log('Failed to prepare database: ' . $exception->getMessage());
    throw $exception;
}

$programService = new Program();

$programsPath = __DIR__ . '/../programs/';
$jsonFiles = glob($programsPath . '*.json') ?: [];

foreach ($jsonFiles as $file) {
    if (!is_file($file)) {
        continue;
    }

    $jsonContent = file_get_contents($file);
    if ($jsonContent === false) {
        error_log("Failed to read file: {$file}");
        continue;
    }

    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON in file {$file}: " . json_last_error_msg());
        continue;
    }

    if (!isset($data['program_name'], $data['scopes'], $data['ooscopes'], $data['config'])) {
        error_log("Missing required keys in file {$file}");
        continue;
    }

    try {
        $programService->upsert_program(
            $data['program_name'],
            (array) $data['scopes'],
            (array) $data['ooscopes'],
            (array) $data['config']
        );
    } catch (\Throwable $exception) {
        error_log("Failed to upsert program from {$file}: " . $exception->getMessage());
    }
}

echo "\033[36m--------------------------------------------------------\n";

echo "\n\033[36m
  ██████╗██╗  ██╗ █████╗ ███╗   ██╗ ██████╗ ██╗███████╗
 ██╔════╝██║  ██║██╔══██╗████╗  ██║██╔════╝ ██║╚══███╔╝
 ██║     ███████║███████║██╔██╗ ██║██║  ███╗██║  ███╔╝ 
 ██║     ██╔══██║██╔══██║██║╚██╗██║██║   ██║██║ ███╔╝  
 ╚██████╗██║  ██║██║  ██║██║ ╚████║╚██████╔╝██║███████╗
  ╚═════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝  ╚═══╝ ╚═════╝ ╚═╝╚══════╝
\n";

echo "\033[36m--------------------------------------------------------\n\n";
echo "\033[35m[+] Program synchronization completed.\n\033[0m";