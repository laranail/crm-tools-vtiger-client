<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use DateTimeInterface;
use Exception;
use RuntimeException;
use function date_create_immutable_from_format;
use function microtime;
use function count;
use function sprintf;

/**
 * Utility class to enable very simplistic timing.
 *
 * Usage example:
 *
 *     $t = new SimpleTimer();
 *     // do something here
 *     echo $t;
 *
 * You can also mark positions:
 *
 *     $t->mark('Step 1');
 *     sleep(2.24);
 *     $t->mark();
 *     usleep(327);
 *     $t->stop();
 *     echo $t;
 */

final class SimpleTimer
{

    protected $times = [];
    protected $started;
    protected $stopped;

    /**
     * Timer constructor
     */
    public function __construct()
    {
        $this->times   = [$this->getTimestamp('Start timer')];
        $this->started = true;
        $this->stopped = false;
    }

    /**
     * Create a timestamp object
     *
     * @param null|string $message
     * @return object
     */
    protected function getTimestamp(?string $message = null): object
    {
        return (object)[
            'time'    => date_create_immutable_from_format('U.u', (string) microtime(true)),
            'message' => $message
        ];
    }

    /**
     * Get formatted date/time.
     *
     * @param DateTimeInterface $datetime
     * @return string
     */
    protected function getFormatted(DateTimeInterface $datetime): string
    {
        return $datetime->format('Y-m-d H:i:s');
    }

    /**
     * Represent the delta between the start and end times.
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return string
     */
    protected function getDiff(DateTimeInterface $start, DateTimeInterface $end): string
    {
        return $end->diff($start, true)->format('%dd %hh %im %s.%Fs');
    }

    /**
     * Mark the next timer spot
     *
     * @param null|string $message
     * @return $this
     * @throws RuntimeException
     */
    public function mark(?string $message = null): self
    {
        if ($this->stopped) {
            return $this;
        }
        if ($this->started !== true) {
            throw new RuntimeException('You have not started a timer');
        }
        $this->times[] = $this->getTimestamp($message);
        return $this;
    }

    /**
     * Stop the timer
     *
     * @return $this
     * @throws RuntimeException
     */
    public function stop(): self
    {
        if ($this->stopped) {
            return $this;
        }
        if ($this->started !== true) {
            throw new RuntimeException('You have not started a timer');
        }
        $this->times[] = $this->getTimestamp('End timer');
        $this->stopped = true;
        return $this;
    }

    /**
     * Output the timer details.
     *
     * If the timer has not been stopped it will just give the output with the
     * start time and the delta since then.  If the timer has been stopped it
     * will give a fully summary with any marked positions.
     *
     * @return string
     */
    public function __toString(): string
    {
        if ($this->started !== true) {
            return 'You have not started a timer';
        }
        try {
            if (!$this->stopped) {
                $current = $this->getTimestamp('So far');
                return sprintf("Started %s, current delta %s\n",
                    $this->getFormatted($this->times[0]->time),
                    $this->getDiff($current->time, $this->times[0]->time)
                );
            }
            $output = '';
            $total = count($this->times);
            foreach ($this->times as $i => $timeValue) {
                if (!$i) {
                    $output .= sprintf("Started %s\n", $this->getFormatted($timeValue->time));
                } elseif ($i === ($total - 1)) {
                    $output .= sprintf("Ended %s, total time %s\n",
                        $this->getFormatted($timeValue->time),
                        $this->getDiff($this->times[0]->time, $timeValue->time)
                    );
                } else {
                    $output .= sprintf("\tΔ %s%s\n",
                        $this->getDiff($this->times[$i - 1]->time, $timeValue->time),
                        $timeValue->message ? " ({$timeValue->message})" : ''
                    );
                }
            }
            return $output;
        } catch (Exception $e) {
            return 'Cannot determine timer: ' . $e->getMessage();
        }
    }
}