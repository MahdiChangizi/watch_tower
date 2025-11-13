<?php
declare(strict_types=1);

namespace App\Tools;

use RuntimeException;

final class SendDiscordMessage
{
    private const USER_AGENT = 'watch_tower/1.0';

    private ?string $webhook;
    private const MUTE_ONCE_FLAG = 'config/discord_mute_once.flag';

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
        if (self::notificationsDisabled() || self::consumeMuteOnceFlag()) {
            error_log('SendDiscordMessage: Discord notifications muted.');
            return null;
        }

        $webhook = getenv('WEBHOOK_URL');
        if ($webhook === false || $webhook === '') {
            $webhook = $_ENV['WEBHOOK_URL'] ?? null;
        }

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

    private static function notificationsDisabled(): bool
    {
        $envValue = getenv('DISCORD_NOTIFICATIONS_ENABLED');
        if (self::isExplicitlyFalse($envValue)) {
            return true;
        }

        $envValue = $_ENV['DISCORD_NOTIFICATIONS_ENABLED'] ?? null;
        if (self::isExplicitlyFalse($envValue)) {
            return true;
        }

        return false;
    }

    private static function isExplicitlyFalse($value): bool
    {
        if ($value === false || $value === null) {
            return false;
        }

        $normalized = strtolower((string) $value);

        return in_array($normalized, ['0', 'false', 'off', 'no'], true);
    }

    private static function consumeMuteOnceFlag(): bool
    {
        static $cachedResult = null;

        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $file = dirname(__DIR__, 2) . '/' . self::MUTE_ONCE_FLAG;

        if (!is_file($file)) {
            $cachedResult = false;
            return $cachedResult;
        }

        @unlink($file);
        error_log("SendDiscordMessage: Consumed mute-once flag, notifications will be suppressed for this run.");

        $cachedResult = true;

        return $cachedResult;
    }
}
