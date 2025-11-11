<?php
namespace App\Helpers;

class Helpers {
    public static function current_time(): string {
        return (new \DateTime())->format('Y-m-d H:i:s');
    }

    public static function dd($data) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        die();
    }

    public function create_temp_file(array $subdomains, string $name): string {
        $timestamp = date('Ymd_His');
        $tempFile = sys_get_temp_dir() . '/' . "{$name}_{$timestamp}_" . uniqid() . '.txt';
        file_put_contents($tempFile, implode("\n", $subdomains) . "\n");
        return $tempFile;
    }


    public function delete_temp_file(string $path): void {
        if (file_exists($path)) {
            unlink($path);
        }
    }

}

