<?php

namespace App\Tools;

class Samoscout {
    private array $subdomains = [];

    public function __construct(string $input) {
        $command = "samoscout -dL {$input} -silent";

        echo "\033[90m[+] Running command: {$command}\033[0m\n";
        exec($command, $output);
        $this->subdomains = $output;
    }

    public function getSubdomains(): array {
        return $this->subdomains;
    }
}

