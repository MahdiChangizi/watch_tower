<?php

namespace App\Tools;

class SendDiscordMessage {
    private ?string $webhook;

    public function __construct() {
        $this->webhook = getenv('WEBHOOK_URL') ?: ($_ENV['WEBHOOK_URL'] ?? null);

        if (!$this->webhook) {
            throw new \RuntimeException("WEBHOOK_URL not set in environment");
        }
    }

    /**
     * Send a message to Discord webhook.
     *
     * @param string $message Plain text or markdown content
     * @return bool True on success (HTTP 204), false on failure
     */
    public function send(string $message): bool {
        $payload = json_encode([
            'content' => $message
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->webhook);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: watch_tower/1.0'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $responseBody = curl_exec($ch);

        if ($responseBody === false) {
            error_log("SendDiscordMessage: curl_exec error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        // curl_getinfo with RESPONSE_CODE (works on modern libcurl)
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($httpCode === null) {
            // fallback to older constant
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);

        if ((int)$httpCode !== 204) {
            // Discord returns 204 No Content on success for simple webhook posts
            error_log("SendDiscordMessage: unexpected status code {$httpCode}. Response body: " . $responseBody);
            return false;
        }

        return true;
    }
}
