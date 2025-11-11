<?php

namespace App\Tools;

class Subfinder {
    private array $subdomains = [];

    public function __construct(string $input, bool $isFile = false) {
        $command = $isFile 
            ? "subfinder -dL {$input} -all -silent"
            : "subfinder -d {$input} -all -silent";

        echo "\033[90m[+] Running command: {$command}\033[0m\n";
        exec($command, $output);
        $this->subdomains = $output;
    }

    public function getSubdomains(): array {
        return $this->subdomains;
    }
}

