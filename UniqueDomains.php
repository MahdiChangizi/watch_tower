#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


$app_url = $_ENV['APP_URL'];

function make_domains_unique($domain_name, $app_url): array {
    $command = "curl $app_url/api.php/http?domain\=$domain_name\&json\=false --silent | httpx -sc -wc --silent";

    $output = [];
    exec($command, $output);

    return remove_repetitive($output);
}

function remove_repetitive($domains) {
    $unique = [];
    $seen_patterns = [];
    
    foreach ($domains as $url) {
        $parts = explode(' ', $url);
        $lastTwo = array_slice($parts, -2);
        $pattern = implode(' ', $lastTwo);
        
        if (!isset($seen_patterns[$pattern])) {
            $seen_patterns[$pattern] = true;
            $unique[] = $url; // keep full URL
        }
    }

    return $unique;
}


$all_data = make_domains_unique($argv[1], $app_url);

foreach($all_data as $data) {
    echo $data . "\r\n";
}

echo "\n\033[36m[+] Making domains unique completed.\033[0m\n";