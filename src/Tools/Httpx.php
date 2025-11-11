<?php

namespace App\Tools;

class Httpx {
    const GRAY = "\033[90m";
    const RESET = "\033[0m";

    private string $inputFile;
    public function __construct(string $inputFile) {
        $this->inputFile = $inputFile;
    }

    private function run(): array {
        $command = "httpx -l {$this->inputFile} -silent -json -favicon -fhr -tech-detect -irh -include-chain -timeout 10 -retries 1 -threads 30 -rate-limit 4 -ports 443 -extract-fqdn -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:108.0) Gecko/20100101 Firefox/108.0'";
        echo self::GRAY . "[+] Running command: {$command}" . self::RESET . "\n";

        $output = [];
        exec($command, $output);

        return $output;
    }

    public function getHttpResponses(): array {
        $output = $this->run();
        $responses = [];

        foreach ($output as $line) {
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $responses[] = $data;
            } else {
                // return raw line if it's not valid JSON (or empty)
                $responses[] = $line;
            }
        }

        return $responses;
    }

    
}

