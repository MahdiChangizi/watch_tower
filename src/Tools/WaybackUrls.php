<?php
declare(strict_types=1);

namespace App\Tools;

final class WaybackUrls
{
    private const BINARY = 'waybackurls';

    private string $target;
    private int $timeout;

    /**
     * @param string $target Domain, URL, or file path supported by waybackurls.
     * @param int    $timeout Maximum execution time in seconds before the process is aborted.
     */
    public function __construct(string $target, int $timeout = 120)
    {
        $target = trim($target);
        if ($target === '') {
            throw new \InvalidArgumentException('WaybackUrls target cannot be empty.');
        }

        $this->target = $target;
        $this->timeout = max(1, $timeout);
    }

    public static function isAvailable(): bool
    {
        $binaryPath = trim((string) shell_exec('command -v ' . self::BINARY));
        return $binaryPath !== '';
    }

    /**
     * Execute waybackurls for the configured target.
     *
     * @param array<int, string> $flags  Additional flags (e.g. ['-dates']).
     * @param bool               $unique Whether to deduplicate results.
     *
     * @return array<int, string> Normalized URL strings.
     */
    public function fetch(array $flags = [], bool $unique = true): array
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('waybackurls binary is not available on PATH.');
        }

        $command = $this->buildCommand($flags);
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start waybackurls process.');
        }

        // No input is passed via STDIN here because the command already echoes the target.
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_close($process);

        if ($status !== 0) {
            $message = trim($stderr) !== '' ? trim($stderr) : 'unknown error';
            throw new \RuntimeException(sprintf('waybackurls exited with status %d: %s', $status, $message));
        }

        $lines = preg_split('/\r\n|\r|\n/', $stdout) ?: [];
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, static fn(string $line): bool => $line !== '');

        if ($unique) {
            $lines = array_values(array_unique($lines));
        } else {
            $lines = array_values($lines);
        }

        return $lines;
    }

    /**
     * @param array<int, string> $flags
     */
    private function buildCommand(array $flags): string
    {
        $flagString = '';
        if (!empty($flags)) {
            $escapedFlags = array_map(
                static function (string $flag): string {
                    $trimmed = trim($flag);
                    if ($trimmed === '') {
                        return '';
                    }
                    return escapeshellarg($trimmed);
                },
                $flags
            );
            $escapedFlags = array_filter($escapedFlags);
            if ($escapedFlags) {
                $flagString = ' ' . implode(' ', $escapedFlags);
            }
        }

        $escapedTarget = escapeshellarg($this->target);
        $command = sprintf('timeout %d bash -c "echo %s | %s%s"', $this->timeout, $escapedTarget, self::BINARY, $flagString);

        return $command;
    }
}

