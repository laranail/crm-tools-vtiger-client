<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Simtabi\Laranail\Console\Tools\Widgets\Header;
use Simtabi\Laranail\Package\Tools\Services\Database\Contracts\SeederConsoleFormatterInterface;

/**
 * Seeder Console Formatter
 *
 * Provides tree-structured console output with status symbols, color coding,
 * and statistics tracking for database seeding operations. Glyphs come from
 * laranail/console's capability-aware Symbols (Unicode, ASCII fallback) and
 * styling from its ConsoleUIFormatter + Header widget.
 */
class SeederConsoleFormatter implements SeederConsoleFormatterInterface
{
    private const array DEFAULT_PADDING = [
        'info' => 2,
        'discovery' => 2,
        'group' => 2,
        'seeder_increment' => 4,
        'error_detail' => 4,
        'summary' => 2,
    ];

    private const array DEFAULT_DISPLAY = [
        'show_duration' => true,
        'show_status_symbols' => true,
        'show_tree_structure' => true,
        'show_group_headers' => true,
        'show_summary' => true,
        'show_error_details' => true,
    ];

    private const array DEFAULT_DISPLAY_WIDTHS = [
        'tree_symbol' => 3,
        'status_symbol' => 1,
        'spaces' => 4,
        'duration_suffix' => 3,
        'done_text' => 5,
    ];

    /**
     * Per-status {@see Symbols} glyph name + colour for a seeder line.
     *
     * @var array<string, array{symbol: string, color: string}>
     */
    private const array STATUS_STYLES = [
        'RUNNING' => ['symbol' => 'running', 'color' => ConsoleUIFormatter::CYAN],
        'DONE' => ['symbol' => 'success', 'color' => ConsoleUIFormatter::GREEN],
        'FAILED' => ['symbol' => 'error', 'color' => ConsoleUIFormatter::RED],
        'SKIPPED' => ['symbol' => 'skipped', 'color' => ConsoleUIFormatter::YELLOW],
    ];

    private ?OutputStyle $output = null;

    private readonly ConsoleUIFormatter $formatter;

    private readonly Symbols $symbols;

    private array $statistics = [];

    private float $startTime = 0.0;

    private array $padding;

    private array $display;

    private array $displayWidths;

    public function __construct(array $config = [])
    {
        $this->padding = array_merge(self::DEFAULT_PADDING, $config['padding'] ?? []);
        $this->display = array_merge(self::DEFAULT_DISPLAY, $config['display'] ?? []);
        $this->displayWidths = array_merge(self::DEFAULT_DISPLAY_WIDTHS, $config['displayWidths'] ?? []);

        $this->formatter = ConsoleUIFormatter::create();
        $this->symbols = Symbols::for(Capabilities::detect());

        $this->initializeConfiguration();
        $this->resetStatistics();
    }

    private function initializeConfiguration(): void
    {
        $this->padding['seeder'] = $this->padding['group'] + $this->padding['seeder_increment'];

        foreach ($this->padding as $key => $value) {
            if (is_numeric($value)) {
                $this->padding[$key] = Str::repeat(' ', (int) $value);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function initializeSession(): void
    {
        $this->startTime = microtime(true);
        $this->resetStatistics();
    }

    /**
     * {@inheritDoc}
     */
    public function displayGroupHeader(string $groupName, int $seederCount, bool $isLast = false): void
    {
        $groupLine = Header::make($groupName)->count($seederCount, 'seeders')->render();
        $this->writeLine($this->padding['group'] . $groupLine);
        $this->incrementStatistic('groups');
    }

    /**
     * {@inheritDoc}
     */
    public function displaySeederStart(string $seederClass, bool $isLast = false): void
    {
        $seederName = $this->extractSeederName($seederClass);
        $seederLine = $this->buildStatusLine($seederName, 'RUNNING', '', $isLast);
        $this->write($this->padding['seeder'] . $seederLine . "\r");
    }

    /**
     * {@inheritDoc}
     */
    public function displaySeederSuccess(string $seederClass, float $duration, bool $isLast = false): void
    {
        $durationMs = number_format($duration * 1000, 2);
        $seederName = $this->extractSeederName($seederClass);

        $dotPadding = $this->getDotPadding($seederName, $durationMs);
        $seederLine = $this->buildStatusLine($seederName, 'DONE', $durationMs, $isLast, '', $dotPadding);

        $this->writeLine($this->padding['seeder'] . $seederLine);
        $this->incrementStatistic('successful');
    }

    /**
     * {@inheritDoc}
     */
    public function displaySeederError(string $seederClass, Exception $exception, float $duration, bool $isLast = false): void
    {
        $durationMs = number_format($duration * 1000);
        $seederName = $this->extractSeederName($seederClass);
        $seederLine = $this->buildStatusLine($seederName, 'FAILED', $durationMs, $isLast);

        $this->writeLine($this->padding['seeder'] . $seederLine);
        $this->displayErrorDetails($exception);
        $this->incrementStatistic('failed');
    }

    /**
     * {@inheritDoc}
     */
    public function displaySeederSkipped(string $seederClass, string $reason, bool $isLast = false): void
    {
        $seederName = $this->extractSeederName($seederClass);
        $seederLine = $this->buildStatusLine($seederName, 'SKIPPED', '', $isLast, $reason);
        $this->writeLine($this->padding['seeder'] . $seederLine);
        $this->incrementStatistic('skipped');
    }

    /**
     * {@inheritDoc}
     */
    public function displaySummary(): void
    {
        if (! $this->display['show_summary']) {
            return;
        }

        $this->writeLine('');
        $this->displaySummaryStatistics();
        $this->displayFinalStatus();
    }

    /**
     * Build a single per-seeder status line: tree branch, coloured status glyph,
     * the seeder name, optional dot-leader, status word and (optionally) the
     * duration or skip reason.
     */
    private function buildStatusLine(
        string $seederName,
        string $status,
        string $durationMs,
        bool $isLast,
        string $reason = '',
        string $dotPadding = ' ',
    ): string {
        $style = self::STATUS_STYLES[$status] ?? ['symbol' => 'bullet', 'color' => ConsoleUIFormatter::GRAY];
        $branch = $this->symbols->get($isLast ? 'last' : 'branch') . ' ';

        $line = $branch
            . $this->formatter->colorize($this->symbols->get($style['symbol']), $style['color'])
            . ' '
            . $seederName;

        if ($durationMs !== '') {
            $line .= $dotPadding . $this->formatter->colorize($durationMs . 'ms', ConsoleUIFormatter::GRAY);
        }

        $line .= ' ' . $this->formatter->colorize($status, $style['color']);

        if ($reason !== '') {
            $line .= ' ' . $this->formatter->colorize('(' . $reason . ')', ConsoleUIFormatter::GRAY);
        }

        return $line;
    }

    private function displayErrorDetails(Exception $exception): void
    {
        if (! $this->display['show_error_details']) {
            return;
        }

        $errorLine = $this->padding['error_detail'] . $this->formatter->colorize(
            'Error: ' . $exception->getMessage(),
            ConsoleUIFormatter::RED
        );
        $this->writeLine($errorLine);
    }

    private function displaySummaryStatistics(): void
    {
        $this->writeLine($this->statisticsLine('Groups', $this->statistics['groups'], ConsoleUIFormatter::CYAN));
        $this->writeLine($this->statisticsLine('Successful', $this->statistics['successful'], ConsoleUIFormatter::GREEN));

        if ($this->statistics['failed'] > 0) {
            $this->writeLine($this->statisticsLine('Failed', $this->statistics['failed'], ConsoleUIFormatter::RED));
        }

        if ($this->statistics['skipped'] > 0) {
            $this->writeLine($this->statisticsLine('Skipped', $this->statistics['skipped'], ConsoleUIFormatter::YELLOW));
        }

        $this->writeLine('');
    }

    private function statisticsLine(string $label, int $count, string $color): string
    {
        return $this->padding['summary']
            . $this->formatter->colorize($label . ':', $color, true)
            . ' '
            . $this->formatter->colorize((string) $count, $color);
    }

    private function displayFinalStatus(): void
    {
        $total = $this->statistics['successful'] + $this->statistics['failed'] + $this->statistics['skipped'];
        $duration = microtime(true) - $this->startTime;

        if ($this->statistics['failed'] === 0) {
            $status = $this->formatter->colorize('All seeders completed successfully!', ConsoleUIFormatter::GREEN, true);
        } else {
            $failed = $this->statistics['failed'];
            $status = $this->formatter->colorize(
                "Completed with {$failed} " . Str::plural('failure', $failed)
                    . " out of {$total} " . Str::plural('seeder', $total),
                ConsoleUIFormatter::YELLOW,
                true
            );
        }

        $this->writeLine($this->padding['summary'] . $status);
        $this->writeLine($this->padding['summary'] . $this->formatter->colorize(
            'Total execution time: ' . number_format($duration * 1000, 2) . 'ms',
            ConsoleUIFormatter::GRAY
        ));
    }

    private function incrementStatistic(string $type): void
    {
        $this->statistics[$type] = ($this->statistics[$type] ?? 0) + 1;
    }

    private function writeLine(string $line): void
    {
        if ($this->output instanceof OutputStyle) {
            $this->output->writeln($line);
        }
    }

    private function write(string $text): void
    {
        if ($this->output instanceof OutputStyle) {
            $this->output->write($text);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function writeInfo(string $message): void
    {
        $badge = ConsoleUIFormatter::badge('INFO', ConsoleUIFormatter::BADGE_STYLE_INFO);
        $this->writeLine($this->padding['info'] . $badge . ' ' . $this->formatter->colorize($message, ConsoleUIFormatter::BLUE));
    }

    /**
     * {@inheritDoc}
     */
    public function writeDiscovery(string $message): void
    {
        $badge = ConsoleUIFormatter::badge('DISCOVERY', ConsoleUIFormatter::BADGE_STYLE_INFO);
        $this->writeLine($this->padding['discovery'] . $badge . ' ' . $this->formatter->colorize($message, ConsoleUIFormatter::CYAN));
    }

    /**
     * {@inheritDoc}
     */
    public function writeSuccess(string $message): void
    {
        $badge = ConsoleUIFormatter::badge('SUCCESS', ConsoleUIFormatter::BADGE_STYLE_SUCCESS);
        $this->writeLine($this->padding['info'] . $badge . ' ' . $this->formatter->colorize($message, ConsoleUIFormatter::GREEN));
    }

    /**
     * {@inheritDoc}
     */
    public function writeWarning(string $message): void
    {
        $badge = ConsoleUIFormatter::badge('WARNING', ConsoleUIFormatter::BADGE_STYLE_WARNING);
        $this->writeLine($this->padding['info'] . $badge . ' ' . $this->formatter->colorize($message, ConsoleUIFormatter::YELLOW));
    }

    /**
     * {@inheritDoc}
     */
    public function writeError(string $message): void
    {
        $badge = ConsoleUIFormatter::badge('ERROR', ConsoleUIFormatter::BADGE_STYLE_DANGER);
        $this->writeLine($this->padding['info'] . $badge . ' ' . $this->formatter->colorize($message, ConsoleUIFormatter::RED));
    }

    private function extractSeederName(string $seederClass): string
    {
        $parts = Str::of($seederClass)->explode('\\');
        $className = (string) $parts->last();

        // Strip a trailing "Seeder" for display (PostSeeder → Post); returns
        // the name unchanged when there's no such suffix.
        return Str::beforeLast($className, 'Seeder') ?: $className;
    }

    private function getDotPadding(string $seederName, string $duration): string
    {
        $maxWidth = 50;

        $usedWidth =
            $this->displayWidths['tree_symbol'] +
            $this->displayWidths['status_symbol'] +
            $this->displayWidths['spaces'] +
            Str::length($duration) + $this->displayWidths['duration_suffix'] +
            $this->displayWidths['done_text'];

        $availableWidth = $maxWidth - $usedWidth;
        $seederNameWidth = Str::length($seederName);

        if ($seederNameWidth >= $availableWidth) {
            return ' ';
        }

        $dotCount = $availableWidth - $seederNameWidth;

        return Str::repeat('.', (int) max(1, $dotCount));
    }

    /**
     * {@inheritDoc}
     */
    public function resetStatistics(): void
    {
        $this->statistics = [
            'groups' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function setOutput(?OutputStyle $output): void
    {
        $this->output = $output;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionDuration(): float
    {
        return microtime(true) - $this->startTime;
    }
}
