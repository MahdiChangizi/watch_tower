<?php

namespace App\Tools;

class SendDiscordMessage {
    private string $webhook;

    public function __construct() {
        $this->webhook = $_ENV['WEBHOOK_URL'] ?? null;
        if (!$this->webhook) {
            die("WEBHOOK_URL not set in .env");
        }
    }

    public function send(string $message) {
        // $message, $this->webhook
       return true;
    }
}
