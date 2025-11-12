<?php
declare(strict_types=1);

namespace App\Tools;

use RuntimeException;

final class SendDiscordMessage
{
    private const USER_AGENT = 'watch_tower/1.0';

    private ?string $webhook;

    public function __construct(?string $webhook = null)
    {
        $this->webhook = $webhook
            ?? getenv('WEBHOOK_URL')
            ?? ($_ENV['WEBHOOK_URL'] ?? null);

        if ($this->webhook === false || $this->webhook === '') {
            $this->webhook = null;
        }
    }

    public static function createOrNull(): ?self
    {
        $webhook = $_ENV['WEBHOOK_URL'];
        if (!$webhook) {
            error_log('SendDiscordMessage: WEBHOOK_URL not configured; notifications disabled.');
            return null;
        }

        return new self($webhook);
    }

    /**
     * @param string $message Plain text or markdown content
     */
    public function send(string $message): bool
    {
        if (!$this->webhook) {
            return false;
        }

        $payload = json_encode(['content' => $message], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new RuntimeException('Unable to encode Discord payload.');
        }

        $ch = curl_init($this->webhook);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: ' . self::USER_AGENT,
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            error_log('SendDiscordMessage: curl_exec error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($httpCode === null) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);

        if ((int) $httpCode !== 204) {
            error_log("SendDiscordMessage: unexpected status code {$httpCode}. Response body: {$responseBody}");
            return false;
        }

        return true;
    }
}
