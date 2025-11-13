#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Urls\UrlScanner;

function print_usage(): void
{
    $script = basename(__FILE__);
    echo <<<USAGE
Usage: php {$script} <program_name> [options]

Options:
  --save                Persist matched URLs into the database (table: urls)
  --params=/path/file   Use a custom parameter wordlist (default: common_param.txt)
  --limit=N             Limit the number of wayback URLs processed per scope
  --flag=value          Pass an additional flag to waybackurls (repeatable)
  --source=name         Override the source tag stored with persisted URLs (default: waybackurls)
  --help                Show this help message

Examples:
  php {$script} discourse
  php {$script} discourse --save --limit=500
  php {$script} discourse --params=/tmp/custom_params.txt --flag=-dates

USAGE;
}

$args = $argv;
array_shift($args); // remove script name

if (empty($args) || in_array('--help', $args, true) || in_array('-h', $args, true)) {
    print_usage();
    exit(empty($args) ? 1 : 0);
}

$programName = array_shift($args);

$options = [
    'save' => false,
    'paramsFile' => null,
    'limit' => null,
    'flags' => [],
    'source' => null,
];

foreach ($args as $arg) {
    if ($arg === '--save') {
        $options['save'] = true;
        continue;
    }

    if (str_starts_with($arg, '--params=')) {
        $options['paramsFile'] = substr($arg, strlen('--params='));
        continue;
    }

    if (str_starts_with($arg, '--limit=')) {
        $value = substr($arg, strlen('--limit='));
        $options['limit'] = is_numeric($value) ? (int) $value : null;
        continue;
    }

    if (str_starts_with($arg, '--flag=')) {
        $flagValue = substr($arg, strlen('--flag='));
        if ($flagValue !== '') {
            $options['flags'][] = $flagValue;
        }
        continue;
    }

    if (str_starts_with($arg, '--source=')) {
        $options['source'] = substr($arg, strlen('--source='));
        continue;
    }

    fwrite(STDERR, "[!] Unknown option: {$arg}\n");
    print_usage();
    exit(1);
}

$scanner = new UrlScanner();

try {
    $result = $scanner->scan($programName, $options);
} catch (\Throwable $throwable) {
    fwrite(STDERR, "[!] UrlScanner error: {$throwable->getMessage()}\n");
    exit(1);
}

$matches = $result['matches'];
$matchCount = (int) ($result['match_count'] ?? 0);
$errors = $result['errors'] ?? [];
$persisted = $result['persisted'] ?? null;

echo "\033[36m[+] Program:\033[0m {$result['program']}\n";
echo "\033[36m[+] Scopes loaded:\033[0m " . count($result['scopes']) . "\n";
echo "\033[36m[+] Parameter wordlist:\033[0m {$result['parameters_source']} (" . $result['parameters_loaded'] . " entries)\n";
echo "\033[36m[+] waybackurls total URLs:\033[0m {$result['total_urls']} (unique: {$result['unique_urls']})\n";
echo "\033[36m[+] Matches found:\033[0m {$matchCount}\n";

if ($matchCount > 0) {
    echo "\n\033[32m[+] Interesting URLs:\033[0m\n";
    foreach ($matches as $entry) {
        $params = isset($entry['parameters']) ? implode(', ', (array) $entry['parameters']) : '';
        $scope = $entry['scope'] ?? 'n/a';
        echo "  - {$entry['url']}  \033[90m(scope: {$scope}; params: {$params})\033[0m\n";
    }
}

if ($options['save'] && $persisted) {
    $inserted = $persisted['inserted'] ?? 0;
    $updated = $persisted['updated'] ?? 0;
    echo "\n\033[35m[+] Persistence summary:\033[0m inserted {$inserted}, updated {$updated}\n";
}

if (!empty($errors)) {
    echo "\n\033[33m[!] Issues encountered:\033[0m\n";
    foreach ($errors as $error) {
        $scope = $error['scope'] ?? 'n/a';
        $message = $error['message'] ?? 'Unknown error';
        echo "  - {$scope}: {$message}\n";
    }
}

echo "\n\033[36m[+] UrlScanner completed.\033[0m\n";

