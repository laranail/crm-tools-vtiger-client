<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use BackedEnum;
use Closure;
use DateTimeZone;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Contracts\CronExpressible;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;
use Simtabi\Laranail\Package\Tools\Enums\Environment;
use Simtabi\Laranail\Package\Tools\Enums\Weekday;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;
use Simtabi\Laranail\Package\Tools\Support\DeferredCallQueue;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\CronBuilder;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\TimeOfDay;

/**
 * one fluent surface for scheduling a package command, composed from
 * injected collaborators: the CronBuilder owns the cron-expressible
 * frequency vocabulary (implemented once, shared verbatim), the
 * DeferredCallQueue captures everything cron cannot express (sub-minute
 * frequencies, runtime constraints, execution modifiers) for replay on the
 * real scheduler event, and the ConfigGate owns config gating.
 *
 * dispatch: a method that exists on the CronBuilder forwards there; any
 * other call is recorded and replayed on the Event at schedule time with
 * validation — so the full laravel scheduler vocabulary works fluently
 * without being reimplemented.
 *
 * CronBuilder-native frequencies (forwarded directly):
 *
 * @method self daily()
 * @method self weekly(Weekday|int $day = \Simtabi\Laranail\Package\Tools\Enums\Weekday::Sunday)
 * @method self monthly(int $day = 1)
 * @method self quarterly()
 * @method self yearly()
 * @method self twiceWeekly(Weekday|int $first = \Simtabi\Laranail\Package\Tools\Enums\Weekday::Monday, Weekday|int $second = \Simtabi\Laranail\Package\Tools\Enums\Weekday::Thursday)
 * @method self twiceMonthly(int $first = 1, int $second = 16)
 * @method self biWeekly()
 * @method self biMonthly()
 * @method self at(TimeOfDay|string $time)
 * @method self everyMinutes(int $step)
 * @method self everyHours(int $step)
 * @method self everyDays(int $step)
 * @method self everyMonths(int $step)
 * @method self weekdays()
 * @method self weekends()
 *
 * Recorded and replayed on the scheduler Event (any Laravel
 * Illuminate\Console\Scheduling\Event method works — representative set):
 * @method self hourly()
 * @method self everyFiveMinutes()
 * @method self everyOddHour(int|string $offset = 0)
 * @method self everySecond()
 * @method self withoutOverlapping(int $expiresAt = 1440)
 * @method self onOneServer()
 * @method self runInBackground()
 * @method self timezone(BackedEnum|DateTimeZone|string $timezone)
 * @method self environments(Environment|string ...$environments)
 * @method self name(string $description)
 *
 * @implements Arrayable<string, mixed>
 */
final class ScheduledCommandDefinition implements Arrayable, Jsonable, JsonSerializable
{
    private ?ConfigGate $gate = null;

    private ?string $cadenceConfigKey = null;

    private ?string $cadenceConfigDefault = null;

    private bool $configCadenceApplied = false;

    public function __construct(
        private readonly string $command,
        private readonly CronBuilder $cron = new CronBuilder,
        private readonly DeferredCallQueue $eventCalls = new DeferredCallQueue,
    ) {}

    public static function make(string $command, ?CronBuilder $cron = null): self
    {
        return new self($command, $cron ?? new CronBuilder);
    }

    /**
     * @param array<int, mixed> $args
     */
    public function __call(string $method, array $args): self
    {
        if (method_exists($this->cron, $method)) {
            $this->cron->{$method}(...$args);

            return $this;
        }

        $this->eventCalls->record($method, $args);

        return $this;
    }

    /**
     * seed the embedded builder with a full expression.
     */
    public function cron(CronExpressible|string $expression): self
    {
        $this->cron->fromExpression(
            $expression instanceof CronExpressible ? $expression->toExpression() : $expression,
        );

        return $this;
    }

    /**
     * shorthand cadence: an enum case, an expression, a scheduler-method
     * string ('daily', 'dailyAt:02:00', 'twiceDaily:1,13', or a raw
     * '0 2 * * *'), or a closure receiving the Event.
     */
    public function cadence(Cadence|CronExpressible|string|Closure $cadence): self
    {
        if ($cadence instanceof Closure) {
            $this->eventCalls->recordClosure($cadence);

            return $this;
        }

        if ($cadence instanceof CronExpressible) {
            return $this->cron($cadence);
        }

        $this->applyCadenceString($cadence instanceof Cadence ? $cadence->value : $cadence);

        return $this;
    }

    /**
     * read the cadence from config at schedule time. a missing key falls
     * back to $default; an explicit null/false value (or a missing key
     * with a null default) skips scheduling entirely.
     */
    public function cadenceFromConfig(string $key, Cadence|CronExpressible|string|null $default = null): self
    {
        $this->cadenceConfigKey = $key;
        $this->cadenceConfigDefault = match (true) {
            $default instanceof Cadence => $default->value,
            $default instanceof CronExpressible => $default->toExpression(),
            default => $default,
        };

        return $this;
    }

    public function whenConfig(string $key, bool $default = true): self
    {
        $this->gate = ConfigGate::make($key, $default)->truthy();

        return $this;
    }

    public function whenConfigNotNull(string $key): self
    {
        $this->gate = ConfigGate::make($key)->notNull();

        return $this;
    }

    /**
     * full-control escape hatch, applied after everything else.
     */
    public function configure(Closure $callback): self
    {
        $this->eventCalls->recordClosure($callback);

        return $this;
    }

    public function command(): string
    {
        return $this->command;
    }

    /**
     * schedule-time resolution: evaluates the gate and any config-driven
     * cadence. returns false when this command must not be scheduled.
     */
    public function shouldSchedule(): bool
    {
        if ($this->gate instanceof ConfigGate && ! $this->gate->passes()) {
            return false;
        }

        if ($this->cadenceConfigKey === null) {
            return true;
        }

        $value = config($this->cadenceConfigKey, $this->cadenceConfigDefault);

        if ($value instanceof Cadence) {
            $value = $value->value;
        }

        if ($value === null || $value === false) {
            return false;
        }

        if (is_string($value) && $value !== '') {
            // apply once per definition: the schedule can resolve more than
            // once per process (tests, octane), and re-applying would record
            // the same deferred event calls again — double replay
            if (! $this->configCadenceApplied) {
                $this->applyCadenceString($value);
                $this->configCadenceApplied = true;
            }

            return true;
        }

        return false; // malformed config value: fail closed, never guess
    }

    /**
     * apply the frequency and every deferred call to the real event.
     */
    public function applyTo(Event $event): void
    {
        if (! $this->cron->isTouched() && $this->eventCalls->isEmpty()) {
            $this->cron->daily(); // a bare definition schedules daily, never every-minute
        }

        if ($this->cron->isTouched()) {
            $event->cron($this->cron->toExpression());
        }

        $this->eventCalls->replayOn($event);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'cron' => $this->cron->isTouched() ? $this->cron->toArray() : null,
            'deferred_calls' => array_map(
                static fn (array|Closure $call): array|string => $call instanceof Closure ? 'closure' : $call,
                $this->eventCalls->all(),
            ),
            'gate' => $this->gate?->toArray(),
            'cadence_config' => $this->cadenceConfigKey === null ? null : [
                'key' => $this->cadenceConfigKey,
                'default' => $this->cadenceConfigDefault,
            ],
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * one pipeline for fluent shorthands and config strings: known enum
     * cadence, then raw 5-field cron, then 'method[:comma,args]' routed
     * through the same cron-first/event-fallback dispatch as __call.
     */
    private function applyCadenceString(string $value): void
    {
        $value = trim($value);

        $known = Cadence::tryFrom($value);

        if ($known instanceof Cadence) {
            $this->dispatchCadenceMethod($known->value, []);

            return;
        }

        // a raw 5-field cron: five tokens shaped like their field (numeric
        // minute/hour; names allowed in month/day-of-week) — any five words
        // ('daily and more words here') must not be swallowed as cron, but
        // fall through to method dispatch and fail loudly at replay
        if (preg_match('/^[\d*,\/-]+\s+[\d*,\/-]+\s+[\d*,\/LW?-]+\s+[\dA-Za-z*,\/-]+\s+[\dA-Za-z*,\/L#?-]+$/', $value) === 1) {
            $this->cron($value);

            return;
        }

        [$method, $argString] = array_pad(explode(':', $value, 2), 2, null);

        $args = $argString === null ? [] : array_map(
            // strict_types: scheduler methods take ints, config gives strings
            static fn (string $arg): int|float|string => is_numeric($arg) ? $arg + 0 : $arg,
            array_map(trim(...), explode(',', $argString)),
        );

        $this->dispatchCadenceMethod((string) $method, $args);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function dispatchCadenceMethod(string $method, array $args): void
    {
        if (method_exists($this->cron, $method)) {
            $this->cron->{$method}(...$args);

            return;
        }

        $this->eventCalls->record($method, $args);
    }
}
