<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Scheduling;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * a fluent time-of-day value: 24h and 12h notation in, minute-level
 * arithmetic (wrapping across midnight), canonical 'H:i' out — what cron
 * and the scheduler consume.
 *
 * @implements Arrayable<string, int|string>
 */
final readonly class TimeOfDay implements Arrayable, Jsonable, JsonSerializable
{
    private function __construct(
        private int $hour,
        private int $minute,
    ) {
        if ($hour < 0 || $hour > 23) {
            throw new InvalidArgumentException(sprintf('hour must be 0-23, got %d', $hour));
        }

        if ($minute < 0 || $minute > 59) {
            throw new InvalidArgumentException(sprintf('minute must be 0-59, got %d', $minute));
        }
    }

    public static function at(int $hour, int $minute = 0): self
    {
        return new self($hour, $minute);
    }

    /**
     * accepts both notations: '17:30', '5:30pm', '5:30 PM', '5pm', '05:00'.
     */
    public static function parse(string $time): self
    {
        $normalized = strtolower(trim($time));

        if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)$/', $normalized, $m) === 1) {
            $hour = (int) $m[1];
            $minute = (int) $m[2]; // optional group: '' when absent, (int) '' === 0

            if ($hour < 1 || $hour > 12) {
                throw new InvalidArgumentException(sprintf('12-hour clock hour must be 1-12, got %d in "%s"', $hour, $time));
            }

            return $m[3] === 'pm' ? self::pm($hour, $minute) : self::am($hour, $minute);
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $normalized, $m) === 1) {
            return new self((int) $m[1], (int) $m[2]);
        }

        throw new InvalidArgumentException(sprintf('unparseable time "%s"; use "H:MM", "H:MMam", or "H pm"', $time));
    }

    public static function am(int $hour, int $minute = 0): self
    {
        self::assertTwelveHour($hour);

        return new self($hour === 12 ? 0 : $hour, $minute);
    }

    public static function pm(int $hour, int $minute = 0): self
    {
        self::assertTwelveHour($hour);

        return new self($hour === 12 ? 12 : $hour + 12, $minute);
    }

    public function plusMinutes(int $minutes): self
    {
        $total = ($this->hour * 60 + $this->minute + $minutes) % 1440;

        if ($total < 0) {
            $total += 1440;
        }

        return new self(intdiv($total, 60), $total % 60);
    }

    public function minusMinutes(int $minutes): self
    {
        return $this->plusMinutes(-$minutes);
    }

    public function plusHours(int $hours): self
    {
        return $this->plusMinutes($hours * 60);
    }

    public function hour(): int
    {
        return $this->hour;
    }

    public function minute(): int
    {
        return $this->minute;
    }

    /**
     * the canonical form ('17:30') — what cron fields and scheduler
     * methods consume.
     */
    public function format24(): string
    {
        return sprintf('%02d:%02d', $this->hour, $this->minute);
    }

    public function format12(): string
    {
        $meridiem = $this->hour < 12 ? 'AM' : 'PM';
        $hour = $this->hour % 12;

        return sprintf('%d:%02d %s', $hour === 0 ? 12 : $hour, $this->minute, $meridiem);
    }

    /**
     * @return array{hour: int, minute: int, formatted: string}
     */
    public function toArray(): array
    {
        return ['hour' => $this->hour, 'minute' => $this->minute, 'formatted' => $this->format24()];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * @return array{hour: int, minute: int, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function assertTwelveHour(int $hour): void
    {
        if ($hour < 1 || $hour > 12) {
            throw new InvalidArgumentException(sprintf('12-hour clock hour must be 1-12, got %d', $hour));
        }
    }
}
