<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Config;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\Lang;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Support\TimeFormat;
use Stringable;

/**
 * An execution-summary block: statistics, performance metrics, error details and
 * final status badges, rendered from a stats array.
 *
 * The returned string carries raw ANSI (echo-safe). Expected `$stats` keys:
 *  - total, success, failed          (int)
 *  - totalTime                       (float, milliseconds)
 *  - fastest, slowest                (['class' => string, 'time' => float])
 *  - errors                          (list<['class','type','message']>, optional)
 */
final readonly class Summary implements Stringable
{
    private Capabilities $capabilities;

    /**
     * @param array<string, mixed> $stats
     */
    public function __construct(
        private array $stats,
        private ?string $title = null,
        ?Capabilities $capabilities = null,
    ) {
        $this->capabilities = $capabilities ?? Capabilities::detect();
    }

    /**
     * @param array<string, mixed> $stats
     */
    public static function make(array $stats, ?string $title = null): self
    {
        return new self($stats, $title);
    }

    public function render(): string
    {
        $fmt = ConsoleUIFormatter::create();
        $stats = $this->stats;
        $output = [];

        $title = $this->title ?? Lang::get('widgets.summary.title', 'EXECUTION SUMMARY');

        $dividerWidth = (int) Config::get('summary.divider_width', 60);
        if (ResponsiveWidth::enabled()) {
            $dividerWidth = min($dividerWidth, $this->capabilities->width());
        }
        $output[] = $fmt->colorize(str_repeat('─', max($dividerWidth, 1)), ConsoleUIFormatter::GRAY);
        $output[] = $fmt->colorize($title, ConsoleUIFormatter::BRIGHT_CYAN, true);
        $output[] = '';

        $output[] = $fmt->colorize(Lang::get('widgets.summary.statistics', 'Execution Statistics:'), ConsoleUIFormatter::WHITE, true);
        $output[] = $this->statisticsTable([
            [Lang::get('widgets.summary.total', 'Total'), (string) ($stats['total'] ?? 0), ConsoleUIFormatter::BADGE_STYLE_INFO],
            [Lang::get('widgets.summary.successful', 'Successful'), (string) ($stats['success'] ?? 0), ConsoleUIFormatter::BADGE_STYLE_SUCCESS],
            [Lang::get('widgets.summary.failed', 'Failed'), (string) ($stats['failed'] ?? 0), ($stats['failed'] ?? 0) > 0 ? ConsoleUIFormatter::BADGE_STYLE_DANGER : ConsoleUIFormatter::BADGE_STYLE_SECONDARY],
        ]);
        $output[] = '';

        $output[] = $fmt->colorize(Lang::get('widgets.summary.performance', 'Performance Metrics:'), ConsoleUIFormatter::WHITE, true);
        $output[] = $this->performanceMetrics();
        $output[] = '';

        if (! empty($stats['errors'])) {
            /** @var list<array<string, string>> $errors */
            $errors = $stats['errors'];
            $output[] = $fmt->colorize(Lang::get('widgets.summary.failed_items', 'Failed Items:'), ConsoleUIFormatter::RED, true);
            $output[] = $this->errorDetails($errors);
            $output[] = '';
        }

        $output[] = $this->statusBadges();

        $result = implode("\n", $output);

        // Responsive: clip every line (incl. coloured badges) to the terminal width.
        if (ResponsiveWidth::enabled()) {
            $width = $this->capabilities->width();
            $result = implode("\n", array_map(
                static fn (string $line): string => DisplayWidth::truncateAnsi($line, $width),
                explode("\n", $result),
            ));
        }

        return $result;
    }

    /**
     * @param list<array{0:string,1:string,2:string}> $items label, value, badge style
     */
    private function statisticsTable(array $items): string
    {
        $output = [];

        foreach ($items as [$label, $value, $style]) {
            $output[] = sprintf('   %s %s', str_pad($label . ':', $this->labelPad()), ConsoleUIFormatter::badge($value, $style));
        }

        return implode("\n", $output);
    }

    private function performanceMetrics(): string
    {
        $fmt = ConsoleUIFormatter::create();
        $stats = $this->stats;
        $output = [];

        $totalTime = (float) ($stats['totalTime'] ?? 0);
        $total = max((int) ($stats['total'] ?? 0), 1);

        $output[] = sprintf(
            '   %s %s',
            str_pad(Lang::get('widgets.summary.total_time', 'Total Time:'), $this->labelPad()),
            $fmt->colorize(TimeFormat::fromMillis($totalTime), $this->performanceColor($totalTime), true),
        );

        $output[] = sprintf(
            '   %s %s',
            str_pad(Lang::get('widgets.summary.average_time', 'Average Time:'), $this->labelPad()),
            $fmt->colorize(TimeFormat::fromMillis($totalTime / $total), $this->performanceColor($totalTime / $total)),
        );

        if ($total > 1 && ! empty($stats['fastest']['class'])) {
            /** @var array{class:string,time:float} $fastest */
            $fastest = $stats['fastest'];
            /** @var array{class:string,time:float} $slowest */
            $slowest = $stats['slowest'];

            $output[] = sprintf(
                '   %s %s %s',
                str_pad(Lang::get('widgets.summary.fastest', 'Fastest:'), $this->labelPad()),
                $fmt->colorize($fastest['class'], ConsoleUIFormatter::GREEN),
                $fmt->colorize('(' . TimeFormat::fromMillis((float) $fastest['time']) . ')', ConsoleUIFormatter::GRAY),
            );
            $output[] = sprintf(
                '   %s %s %s',
                str_pad(Lang::get('widgets.summary.slowest', 'Slowest:'), $this->labelPad()),
                $fmt->colorize($slowest['class'], ConsoleUIFormatter::YELLOW),
                $fmt->colorize('(' . TimeFormat::fromMillis((float) $slowest['time']) . ')', ConsoleUIFormatter::GRAY),
            );
        }

        $successRate = ((int) ($stats['success'] ?? 0) / $total) * 100;
        $rateColor = match (true) {
            $successRate >= (float) Config::get('summary.rate_good', 100) => ConsoleUIFormatter::GREEN,
            $successRate >= (float) Config::get('summary.rate_warn', 80) => ConsoleUIFormatter::YELLOW,
            default => ConsoleUIFormatter::RED,
        };

        $output[] = sprintf(
            '   %s %s',
            str_pad(Lang::get('widgets.summary.success_rate', 'Success Rate:'), $this->labelPad()),
            $fmt->colorize(number_format($successRate, 1) . '%', $rateColor, true),
        );

        return implode("\n", $output);
    }

    /**
     * @param list<array<string, string>> $errors
     */
    private function errorDetails(array $errors): string
    {
        $fmt = ConsoleUIFormatter::create();
        $output = [];

        foreach ($errors as $index => $error) {
            $output[] = sprintf('   %d. %s', $index + 1, $fmt->colorize($error['class'] ?? '', ConsoleUIFormatter::YELLOW));

            $message = $error['message'] ?? '';
            $messageMax = (int) Config::get('summary.message_max', 80);
            if (mb_strlen($message) > $messageMax) {
                $message = mb_substr($message, 0, $messageMax - 3) . '...';
            }

            $output[] = sprintf(
                '      %s %s',
                ConsoleUIFormatter::badge(class_basename($error['type'] ?? ''), ConsoleUIFormatter::BADGE_STYLE_DARK),
                $fmt->colorize($message, ConsoleUIFormatter::GRAY),
            );
        }

        return implode("\n", $output);
    }

    private function statusBadges(): string
    {
        $stats = $this->stats;
        $failed = (int) ($stats['failed'] ?? 0);
        $success = (int) ($stats['success'] ?? 0);

        $badges = match (true) {
            $failed === 0 => [[Lang::get('widgets.summary.badge_all_completed', 'ALL COMPLETED'), ConsoleUIFormatter::BADGE_STYLE_SUCCESS]],
            $success > 0 => [
                [Lang::get('widgets.summary.badge_completed_with_errors', 'COMPLETED WITH ERRORS'), ConsoleUIFormatter::BADGE_STYLE_WARNING],
                [Lang::get('widgets.summary.badge_failed', ':count FAILED', ['count' => $failed]), ConsoleUIFormatter::BADGE_STYLE_DANGER],
            ],
            default => [[Lang::get('widgets.summary.badge_all_failed', 'ALL FAILED'), ConsoleUIFormatter::BADGE_STYLE_DANGER]],
        };

        return ConsoleUIFormatter::badges($badges);
    }

    private function labelPad(): int
    {
        return (int) Config::get('summary.label_pad', 16);
    }

    private function performanceColor(float $ms): string
    {
        return match (true) {
            $ms < 100 => ConsoleUIFormatter::GREEN,
            $ms < 500 => ConsoleUIFormatter::GRAY,
            $ms < 1000 => ConsoleUIFormatter::YELLOW,
            default => ConsoleUIFormatter::RED,
        };
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
