<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Progress;

use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ProgressBarWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Progress reporter backed by the experimental symfony/tui full-screen renderer.
 *
 * Each step runs once per loop tick; the bar advances and the label updates
 * between steps. Selected only when the `console.tui.progress` opt-in is enabled
 * AND the symfony/tui package is installed — see {@see ProgressReporterFactory}.
 * Requires a TTY (it drives symfony/tui's event loop), so it is never the default.
 */
final class TuiProgressReporter implements ProgressReporter
{
    public function run(string $label, iterable $steps, callable $callback): void
    {
        $items = is_array($steps) ? array_values($steps) : iterator_to_array($steps, false);
        $total = count($items);

        if ($total === 0) {
            return;
        }

        $tui = new Tui;
        $text = new TextWidget($label);
        $bar = new ProgressBarWidget($total, ProgressBarWidget::FORMAT_NORMAL);

        $tui->add($text)->add($bar);
        $bar->start($total);

        $index = 0;

        $tui->onTick(function (TickEvent $event) use (&$index, $items, $total, $callback, $bar, $text, $tui): bool {
            if ($index >= $total) {
                $bar->finish();
                $tui->stop();

                return false;
            }

            $result = $callback($items[$index]);
            $bar->advance();
            $index++;

            if (is_string($result) && $result !== '') {
                $text->setText($result);
            }

            return $index < $total;
        });

        $tui->run();
    }
}
