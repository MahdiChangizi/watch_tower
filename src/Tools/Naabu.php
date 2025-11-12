<?php
declare(strict_types=1);

namespace App\Tools;

final class Naabu
{
    private const GRAY = "\033[90m";
    private const RESET = "\033[0m";

    private string $inputFile;
    private int $rate;
    private int $concurrency;

    public function __construct(string $inputFile, int $rate = 1000, int $concurrency = 50)
    {
        $this->inputFile = $inputFile;
        $this->rate = max(1, $rate);
        $this->concurrency = max(1, $concurrency);
    }

    private function run(): array
    {
        $command = sprintf(
            "naabu -l %s -rate %d -c %d -json -verify -silent",
            escapeshellarg($this->inputFile),
            $this->rate,
            $this->concurrency
        );

        echo self::GRAY . "[+] Running command: {$command}" . self::RESET . PHP_EOL;

        $output = [];
        exec($command, $output);

        return $output;
    }

    public function getResults(): array
    {
        $output = $this->run();
        $results = [];

        foreach ($output as $line) {
            $decoded = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                continue;
            }
            $results[] = $decoded;
        }

        return $results;
    }
}

