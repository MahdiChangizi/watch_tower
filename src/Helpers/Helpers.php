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
}

