<?php

namespace App\Tools;

class Chaos {
    private array $domain = [];

    public function __construct(string $input) {
        $command = "chaos -dL {$input} -silent | grep -v '^\*'";

        echo "\033[90m[+] Running command: {$command}\033[0m\n";
        exec($command, $output);
        $this->domain = $output;
    }

    public function getSubdomains(): array {
        return $this->domain;
    }
}

