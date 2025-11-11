<?php

namespace App\Tools;

class Dnsx {
    const GRAY = "\033[90m";
    const RESET = "\033[0m";

    private string $inputFile;

    public function __construct(string $inputFile) {
        $this->inputFile = $inputFile;
    }

    private function run(): array {
        $command = "dnsx -l {$this->inputFile} -silent -resp -json -rl 50 -t 20 -r 8.8.4.4,129.250.35.251,208.67.222.222";
        echo self::GRAY . "[+] Running command: {$command}" . self::RESET . "\n";

        $output = [];
        exec($command, $output);

        return $output;
    }

    public function getResolvedDomains(): array {
    $output = $this->run();
    $resolved = [];

    foreach ($output as $line) {
        $data = json_decode($line, true);
        if (!$data || !isset($data['host'])) continue;

        $host = $data['host'];
        $ips = $data['a'] ?? [];
        $cnames = $data['cname'] ?? [];

        $resolved[] = [
            'host' => $host,
            'ips' => array_unique($ips),
            'cnames' => array_unique($cnames),
            'ttl' => $data['ttl'] ?? null,
            'status' => $data['status_code'] ?? null,
            'resolver' => $data['resolver'][0] ?? null,
            'timestamp' => $data['timestamp'] ?? null,
        ];
    }

    return $resolved;
}

}