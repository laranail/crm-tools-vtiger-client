<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Simtabi\Laranail\Package\Tools\Support\ConfigGate;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * a fluent `php artisan about` section. a field value may be ANY type —
 * scalars, `null`, enums (backed → value, pure → name), dates, arrays and
 * objects (rendered as compact JSON), `Stringable`/`__toString` objects,
 * `Arrayable` — or a per-field lazy closure returning any of those,
 * evaluated only when the about command actually runs (no mega-closure
 * closing over everything). optionally gated on config through the shared
 * ConfigGate.
 *
 * Field resolution is failure-safe: a closure that throws (e.g. a
 * `User::count()` against an unmigrated database) renders the section's
 * fallback string instead of crashing `php artisan about` — so authors
 * never have to hand-wrap each field in `rescue()`.
 *
 * @implements Arrayable<string, mixed>
 */
final class AboutSectionDefinition implements Arrayable, Jsonable, JsonSerializable
{
    private const string DEFAULT_FALLBACK = 'n/a';

    /** @var array<string, mixed> */
    private array $fields = [];

    /** @var list<Closure> whole-array lazy sources, merged at resolve time */
    private array $bulkSources = [];

    private ?ConfigGate $gate = null;

    private string $fallback = self::DEFAULT_FALLBACK;

    private function __construct(
        private readonly string $label,
    ) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    /**
     * one field. `$value` may be any type — rendered per {@see stringify()}
     * — or a `Closure` resolved lazily when the about command runs.
     */
    public function field(string $name, mixed $value): self
    {
        $this->fields[$name] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function fields(array $fields): self
    {
        foreach ($fields as $name => $value) {
            // php silently turns numeric-string keys into ints; cast back so
            // a '2026' field name never hits the string-typed field() as int
            $this->field((string) $name, $value);
        }

        return $this;
    }

    /**
     * a whole-array lazy source (`fn (): array => [...]`); merged before
     * individual fields, so explicit field() calls win on name collisions.
     */
    public function fieldsUsing(Closure $source): self
    {
        $this->bulkSources[] = $source;

        return $this;
    }

    /**
     * the placeholder shown when a field's closure (or a bulk source)
     * throws at render time. defaults to `n/a`.
     */
    public function fallback(string $fallback): self
    {
        $this->fallback = $fallback;

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

    public function label(): string
    {
        return $this->label;
    }

    public function shouldDisplay(): bool
    {
        return ! $this->gate instanceof ConfigGate || $this->gate->passes();
    }

    /**
     * evaluate every source and field; called by the about command only.
     * failure-safe: a throwing bulk source is skipped and a throwing field
     * renders {@see $fallback}, so one broken value never crashes the whole
     * `php artisan about` output.
     *
     * @return array<string, string>
     */
    public function resolve(): array
    {
        $resolved = [];

        foreach ($this->bulkSources as $source) {
            try {
                $rows = $source();
            } catch (Throwable $e) {
                // a broken whole-array source drops out entirely — its field
                // names aren't known, so there is nothing to placeholder.
                FailurePolicy::warn('about section source dropped', [
                    'section' => $this->label,
                    'expected' => 'an array of field rows',
                    'actual' => 'threw ' . $e::class,
                    'decision' => 'dropped source, tolerated',
                ]);

                continue;
            }

            foreach ($rows as $name => $value) {
                $resolved[(string) $name] = $this->safeStringify($value);
            }
        }

        foreach ($this->fields as $name => $value) {
            $resolved[$name] = $this->resolveField($value);
        }

        return $resolved;
    }

    private function resolveField(mixed $value): string
    {
        try {
            return $this->stringify($value instanceof Closure ? $value() : $value);
        } catch (Throwable $e) {
            FailurePolicy::warn('about field used fallback', [
                'section' => $this->label,
                'expected' => 'a resolvable field value',
                'actual' => 'threw ' . $e::class,
                'decision' => 'used fallback, tolerated',
            ]);

            return $this->fallback;
        }
    }

    private function safeStringify(mixed $value): string
    {
        try {
            return $this->stringify($value);
        } catch (Throwable $e) {
            FailurePolicy::warn('about field used fallback', [
                'section' => $this->label,
                'expected' => 'a stringifiable value',
                'actual' => 'threw ' . $e::class,
                'decision' => 'used fallback, tolerated',
            ]);

            return $this->fallback;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'fields' => array_map(
                fn (mixed $value): mixed => match (true) {
                    $value instanceof Closure => 'closure',
                    $value === null, is_scalar($value) => $value,
                    // enums/dates/objects/arrays render to their display
                    // string so the serialized form stays JSON-clean.
                    default => $this->stringify($value),
                },
                $this->fields,
            ),
            'bulk_sources' => count($this->bulkSources),
            'fallback' => $this->fallback,
            'gate' => $this->gate?->toArray(),
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
     * render any value to its `php artisan about` display string.
     */
    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof Arrayable => $this->encode($value->toArray()),
            $value instanceof Stringable => (string) $value,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            is_scalar($value) => (string) $value,
            default => $this->encode($value),
        };
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
