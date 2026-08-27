<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Scheduling;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Contracts\CronExpressible;
use Simtabi\Laranail\Package\Tools\Enums\Weekday;

/**
 * a fluent, validated cron-expression designer. standalone by design —
 * usable anywhere a cron string is needed — and the single implementation
 * of the cron-expressible frequency vocabulary (ScheduledCommandDefinition
 * delegates here rather than reimplementing it).
 *
 * cron FIELDS only: runtime constraints (between, environments, overlap)
 * belong to the scheduler event, not the expression. note that a true
 * "every two weeks" is not cron-expressible (there is no week-step field);
 * biWeekly() honestly means twice a week and says so.
 *
 * @implements Arrayable<string, string>
 */
final class CronBuilder implements Arrayable, CronExpressible, Jsonable, JsonSerializable
{
    private string $minute = '*';

    private string $hour = '*';

    private string $dayOfMonth = '*';

    private string $month = '*';

    private string $dayOfWeek = '*';

    private ?string $rawExpression = null;

    private bool $touched = false;

    public static function make(): self
    {
        return new self;
    }

    /**
     * seed from a raw 5-field expression; toExpression() returns it
     * verbatim and field setters are rejected afterwards (one source of
     * truth per builder).
     */
    public function fromExpression(string $expression): self
    {
        $fields = preg_split('/\s+/', trim($expression));

        if (! is_array($fields) || count($fields) !== 5) {
            throw new InvalidArgumentException(sprintf('"%s" is not a 5-field cron expression', $expression));
        }

        $this->rawExpression = implode(' ', $fields);
        $this->touched = true;

        return $this;
    }

    /**
     * @param int|string|array<int, int|string> $minute
     */
    public function minute(int|string|array $minute): self
    {
        return $this->setField('minute', $minute, 0, 59);
    }

    public function everyMinutes(int $step): self
    {
        return $this->minute('*/' . $this->assertStep($step, 59));
    }

    /**
     * @param int|string|array<int, int|string> $hour
     */
    public function hour(int|string|array $hour): self
    {
        return $this->setField('hour', $hour, 0, 23);
    }

    public function everyHours(int $step): self
    {
        // keep an explicitly set minute (minute(30)->everyHours(4) means
        // ':30 past'); only default to :00 when the minute is untouched
        if ($this->minute === '*') {
            $this->minute(0);
        }

        return $this->hour('*/' . $this->assertStep($step, 23));
    }

    /**
     * @param int|string|array<int, int|string> $day
     */
    public function dayOfMonth(int|string|array $day): self
    {
        return $this->setField('dayOfMonth', $day, 1, 31);
    }

    public function everyDays(int $step): self
    {
        return $this->atMidnightUnlessSet()->dayOfMonth('*/' . $this->assertStep($step, 31));
    }

    /**
     * @param int|string|array<int, int|string> $month
     */
    public function month(int|string|array $month): self
    {
        return $this->setField('month', $month, 1, 12);
    }

    public function everyMonths(int $step): self
    {
        return $this->atMidnightUnlessSet()->dayOfMonth(1)->month('*/' . $this->assertStep($step, 12));
    }

    /**
     * @param Weekday|int|string|array<int, Weekday|int|string> $day
     */
    public function dayOfWeek(Weekday|int|string|array $day): self
    {
        return $this->setField('dayOfWeek', $this->fromWeekdays($day), 0, 6);
    }

    /**
     * 'HH:MM' / 'H:MMam' string or a TimeOfDay — sets hour + minute.
     */
    public function at(TimeOfDay|string $time): self
    {
        $time = $time instanceof TimeOfDay ? $time : TimeOfDay::parse($time);

        return $this->minute($time->minute())->hour($time->hour());
    }

    public function daily(): self
    {
        return $this->atMidnightUnlessSet();
    }

    public function weekly(Weekday|int $day = Weekday::Sunday): self
    {
        return $this->atMidnightUnlessSet()->dayOfWeek($day);
    }

    public function monthly(int $day = 1): self
    {
        return $this->atMidnightUnlessSet()->dayOfMonth($day);
    }

    public function quarterly(): self
    {
        return $this->atMidnightUnlessSet()->dayOfMonth(1)->month([1, 4, 7, 10]);
    }

    public function yearly(): self
    {
        return $this->atMidnightUnlessSet()->dayOfMonth(1)->month(1);
    }

    public function twiceWeekly(Weekday|int $first = Weekday::Monday, Weekday|int $second = Weekday::Thursday): self
    {
        return $this->atMidnightUnlessSet()->dayOfWeek([$first, $second]);
    }

    public function twiceMonthly(int $first = 1, int $second = 16): self
    {
        return $this->atMidnightUnlessSet()->dayOfMonth([$first, $second]);
    }

    /**
     * honestly means twice a week: a true every-two-weeks has no cron
     * form (no week-step field) and needs a runtime constraint on the
     * scheduler event instead.
     */
    public function biWeekly(): self
    {
        return $this->twiceWeekly();
    }

    /**
     * honestly means twice a month; see biWeekly() for the reasoning.
     */
    public function biMonthly(): self
    {
        return $this->twiceMonthly();
    }

    public function weekdays(): self
    {
        return $this->atMidnightUnlessSet()->dayOfWeek('1-5');
    }

    public function weekends(): self
    {
        return $this->atMidnightUnlessSet()->dayOfWeek([Weekday::Saturday, Weekday::Sunday]);
    }

    public function isTouched(): bool
    {
        return $this->touched;
    }

    public function toExpression(): string
    {
        if ($this->rawExpression !== null) {
            return $this->rawExpression;
        }

        return implode(' ', [$this->minute, $this->hour, $this->dayOfMonth, $this->month, $this->dayOfWeek]);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        if ($this->rawExpression !== null) {
            return ['expression' => $this->rawExpression];
        }

        return [
            'minute' => $this->minute,
            'hour' => $this->hour,
            'day_of_month' => $this->dayOfMonth,
            'month' => $this->month,
            'day_of_week' => $this->dayOfWeek,
            'expression' => $this->toExpression(),
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function atMidnightUnlessSet(): self
    {
        if ($this->minute === '*') {
            $this->minute(0);
        }

        if ($this->hour === '*') {
            $this->hour(0);
        }

        return $this;
    }

    /**
     * @param int|string|array<int, int|string> $value
     */
    private function setField(string $field, int|string|array $value, int $min, int $max): self
    {
        if ($this->rawExpression !== null) {
            throw new InvalidArgumentException('this builder was seeded with fromExpression(); field setters are unavailable');
        }

        $this->{$field} = $this->normalizeField($value, $min, $max);
        $this->touched = true;

        return $this;
    }

    /**
     * @param int|string|array<int, int|string> $value
     */
    private function normalizeField(int|string|array $value, int $min, int $max): string
    {
        if (is_array($value)) {
            if ($value === []) {
                throw new InvalidArgumentException('a cron field list cannot be empty');
            }

            return implode(',', array_map(fn (int|string $part): string => $this->normalizeField($part, $min, $max), $value));
        }

        if (is_int($value)) {
            $this->assertRange($value, $min, $max);

            return (string) $value;
        }

        $value = trim($value);

        // wildcard, single value ('5'), or range ('5-20') — each optionally
        // stepped ('*/5', '1-30/2'); list parts are handled above. ranges
        // only follow a number (no '*-5'), never invert, and every value
        // atom honours the field's own min-max while the step divisor is
        // 1..max regardless of the field minimum (so '*/0' cannot slip by).
        if (preg_match('/^(?:\*|(?<start>\d+)(?:-(?<end>\d+))?)(?:\/(?<step>\d+))?$/', $value, $m) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid cron field value', $value));
        }

        if (($m['start'] ?? '') !== '') {
            $start = (int) $m['start'];
            $this->assertRange($start, $min, $max);

            if (($m['end'] ?? '') !== '') {
                $end = (int) $m['end'];
                $this->assertRange($end, $min, $max);

                if ($start > $end) {
                    throw new InvalidArgumentException(sprintf('cron range %d-%d is inverted', $start, $end));
                }
            }
        }

        if (($m['step'] ?? '') !== '') {
            $this->assertStep((int) $m['step'], $max);
        }

        return $value;
    }

    /**
     * @param Weekday|int|string|array<int, Weekday|int|string> $day
     * @return int|string|array<int, int|string>
     */
    private function fromWeekdays(Weekday|int|string|array $day): int|string|array
    {
        if ($day instanceof Weekday) {
            return $day->value;
        }

        if (is_array($day)) {
            return array_map(
                static fn (Weekday|int|string $d): int|string => $d instanceof Weekday ? $d->value : $d,
                $day,
            );
        }

        return $day;
    }

    private function assertRange(int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(sprintf('cron field value %d is outside %d-%d', $value, $min, $max));
        }
    }

    private function assertStep(int $step, int $max): int
    {
        if ($step < 1 || $step > $max) {
            throw new InvalidArgumentException(sprintf('step must be 1-%d, got %d', $max, $step));
        }

        return $step;
    }
}
