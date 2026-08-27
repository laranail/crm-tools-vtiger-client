<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Notifications;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Notifications\Contracts\ConsoleChannelInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Standalone console notification channel.
 *
 * Writes timestamped, optionally data-annotated messages to a Symfony console
 * output. Message text and notification level are sanitised so externally
 * sourced content cannot inject formatter markup or terminal escape sequences.
 */
final class ConsoleChannel implements ConsoleChannelInterface
{
    /**
     * Notification levels that map to safe, built-in Symfony output tags.
     */
    private const array ALLOWED_LEVELS = ['info', 'comment', 'error', 'question'];

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], private readonly ?OutputInterface $output = null)
    {
        $this->config = array_merge(['enabled' => true, 'show_data' => true], $config);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    public function getName(): string
    {
        return 'console';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function send(string $message, array $data = []): bool
    {
        $output = $this->output ?? new ConsoleOutput;
        $level = $this->resolveLevel($data['level'] ?? null);

        // escape() neutralises any `<tag>` in the content; sanitizeText() strips
        // terminal control characters. The level is taken from a fixed set.
        $body = OutputFormatter::escape($this->formatMessage($message, $data));

        $output->writeln("<{$level}>{$body}</{$level}>");

        return true;
    }

    private function resolveLevel(mixed $level): string
    {
        return is_string($level) && in_array($level, self::ALLOWED_LEVELS, true) ? $level : 'info';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatMessage(string $message, array $data): string
    {
        $output = '[' . date('Y-m-d H:i:s') . '] ' . ConsoleUIFormatter::sanitizeText($message);

        if ($data !== [] && ($this->config['show_data'] ?? true)) {
            $output .= ' | Data: ' . ConsoleUIFormatter::sanitizeText(json_encode($data) ?: '');
        }

        return $output;
    }
}
