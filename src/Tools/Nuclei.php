<?php
namespace App\Tools;

class Nuclei
{
    public string $inputFile;

    public function __construct(string $inputFile) {
        $this->inputFile = $inputFile;
    }
    
    public function run(): array {
        $command = "nuclei -t ~/nuclei-templates/http/takeovers/ -l {$this->inputFile} -silent";
        echo "\033[90m[+] Running command: {$command}\033[0m\n";

        $output = [];
        exec($command, $output);

        return $output;
    }
}