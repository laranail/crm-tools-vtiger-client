<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Log;

use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Support\Definitions\LogDefinition;
use Stringable;
use Throwable;

/**
 * Per-package logger behind `$package->log()`: writes beautifully
 * formatted lines to a dedicated logfile named after the package, so a
 * host app can always tell WHICH package an entry came from.
 *
 * Deliberately NOT PSR-3 shaped — every level method takes an optional
 * label as its second argument (`->error('boom', 'Seeder')`); reach the
 * compliant logger through `->channel()` when an interface is required.
 *
 * Under the hood the logger registers a real named channel
 * `logging.channels.{vendor}-{package}` (only when the host has not
 * defined that key — a host definition always wins untouched), then
 * resolves through `Log::channel()`, which memoizes named channels and
 * falls back to Laravel's emergency logger instead of throwing.
 *
 * Early-logging: the owning provider arms buffering before
 * configurePackage(); records written before the package's config merges
 * are held (with their original timestamps) and flushed by
 * registerPackageLogging() once the final settings are known. Bounded at
 * {@see self::MAX_BUFFER}; a write while the app is already booted
 * force-flushes so records can never be stranded by a mid-register crash.
 *
 * Logging NEVER throws: failures fall back to the default Log channel,
 * then are swallowed.
 */
final class PackageLogger
{
    private const int MAX_BUFFER = 100;

    /** @var list<array{level: string, message: string, label: ?string, context: array<string, mixed>, time: DateTimeImmutable}> */
    private array $buffer = [];

    private bool $buffering = false;

    private bool $armedWhileBooted = false;

    private bool $channelInjected = false;

    private bool $resolved = false;

    private function __construct(private readonly Package $package) {}

    public static function for(Package $package): self
    {
        return new self($package);
    }

    // ── level methods ────────────────────────────────────────────────

    /** @param array<string, mixed> $context */
    public function emergency(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write(LogLevel::EMERGENCY, $message, $label, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write(LogLevel::ALERT, $message, $label, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write(LogLevel::CRITICAL, $message, $label, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write(LogLevel::ERROR, $message, $label, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write(LogLevel::WARNING, $message, $label, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write(LogLevel::NOTICE, $message, $label, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write(LogLevel::INFO, $message, $label, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write(LogLevel::DEBUG, $message, $label, $context);
    }

    /**
     * INFO-level with a SUCCESS token — for "it worked" moments (installs,
     * publishes, seed runs) that deserve more than a generic info line.
     *
     * @param array<string, mixed> $context
     */
    public function success(string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $context[PackageLogFormatter::LEVEL_LABEL_KEY] = 'SUCCESS';

        $this->write(LogLevel::INFO, $message, $label, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string|Stringable $message, ?string $label = null, array $context = []): void
    {
        $this->write($level, $message, $label, $context);
    }

    // ── lifecycle (wired by the service provider) ────────────────────

    /**
     * Hold writes until markReady() — armed before configurePackage() so
     * early lines re-resolve against the package's merged config.
     */
    public function bufferUntilReady(): void
    {
        $this->buffering = true;
        // Deliberate post-boot buffering (rare, explicit) must not be
        // undone by the stuck-buffer escape below — that valve exists for
        // the pre-boot register flow only.
        $this->armedWhileBooted = $this->hasApp() && app()->isBooted();
    }

    /**
     * Final settings are known: (re)inject the channel config, drop any
     * stale resolved channel, and flush buffered records with their
     * original timestamps.
     */
    public function markReady(): void
    {
        $this->buffering = false;
        $this->forgetChannel();
        $this->flushBuffer();
    }

    /**
     * Invalidate the resolved channel so the next write re-resolves
     * against current config.
     */
    public function forgetChannel(): void
    {
        if ($this->resolved && $this->hasApp()) {
            try {
                Log::forgetChannel($this->channelName());
            } catch (Throwable) {
                // invalidation is best-effort
            }
        }

        $this->resolved = false;
        $this->channelInjected = false;
    }

    // ── introspection / interop ──────────────────────────────────────

    /**
     * The underlying PSR-3 logger — the interop escape hatch for code that
     * needs a LoggerInterface.
     */
    public function channel(): LoggerInterface
    {
        return $this->resolveChannel();
    }

    public function channelName(): string
    {
        return $this->package->configVendor !== null
            ? $this->package->getDashedNamespace()
            : $this->package->name;
    }

    public function enabled(): bool
    {
        $definition = $this->package->getLogDefinition();

        if ($definition instanceof LogDefinition && ! $definition->shouldLog()) {
            return false;
        }

        return (bool) $this->setting('enabled', true);
    }

    // ── internals ────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $context
     */
    private function write(
        string $level,
        string|Stringable $message,
        ?string $label,
        array $context,
        ?DateTimeImmutable $time = null,
    ): void {
        try {
            if (! $this->hasApp() || ! $this->enabled()) {
                return;
            }

            if ($this->buffering) {
                // Stuck-buffer escape: the provider that armed buffering
                // (pre-boot) has finished or died once the app is booted.
                if (! $this->armedWhileBooted && app()->isBooted()) {
                    $this->markReady();
                } elseif (count($this->buffer) >= self::MAX_BUFFER) {
                    $this->buffering = false;
                    $this->flushBuffer();
                } else {
                    $this->buffer[] = [
                        'level' => $level,
                        'message' => (string) $message,
                        'label' => $label,
                        'context' => $context,
                        'time' => new DateTimeImmutable,
                    ];

                    return;
                }
            }

            if ($label !== null && $label !== '') {
                $context[PackageLogFormatter::LABEL_KEY] = $label;
            }

            if ($time instanceof DateTimeImmutable) {
                $context[PackageLogFormatter::TIME_KEY] = $time;
            }

            $this->resolveChannel()->log($level, (string) $message, $context);
        } catch (Throwable) {
            try {
                Log::log($level, (string) $message, $context);
            } catch (Throwable $e) {
                // This is the reporting substrate — it cannot report its own
                // failure through itself — so it falls back to a last-resort
                // local write (rule 8) rather than swallowing silently.
                error_log("[package-tools logger] {$level}: {$message} ({$e->getMessage()})");
            }
        }
    }

    private function flushBuffer(): void
    {
        $pending = $this->buffer;
        $this->buffer = [];

        foreach ($pending as $record) {
            $this->write(
                $record['level'],
                $record['message'],
                $record['label'],
                $record['context'],
                $record['time'],
            );
        }
    }

    private function resolveChannel(): LoggerInterface
    {
        $delegate = $this->setting('channel', null);

        if (is_string($delegate) && $delegate !== '') {
            $this->resolved = true;

            return Log::channel($delegate);
        }

        $name = $this->channelName();

        // A host-defined channel of this name always wins untouched; we
        // only inject (and later refresh) our own definition.
        if ($this->channelInjected || ! config()->has("logging.channels.{$name}")) {
            config()->set("logging.channels.{$name}", $this->channelConfig());
            $this->channelInjected = true;
        }

        $this->resolved = true;

        return Log::channel($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function channelConfig(): array
    {
        $driver = (string) $this->setting('driver', 'daily');
        $displayName = $this->package->configVendor !== null
            ? "{$this->package->configVendor}/{$this->package->name}"
            : $this->package->name;

        $config = [
            'driver' => $driver === 'single' ? 'single' : 'daily',
            'path' => $this->logPath(),
            'level' => (string) $this->setting('level', 'debug'),
            'days' => (int) $this->setting('days', 14),
            'replace_placeholders' => true,
        ];

        if ((string) $this->setting('format', 'line') === 'json') {
            $config['formatter'] = JsonFormatter::class;
        } else {
            $config['formatter'] = PackageLogFormatter::class;
            $config['formatter_with'] = ['package' => $displayName];
        }

        $permission = $this->setting('permission', null);
        if (is_int($permission)) {
            $config['permission'] = $permission;
        }

        return $config;
    }

    private function logPath(): string
    {
        $path = $this->setting('path', null);
        if (is_string($path) && $path !== '') {
            return $path;
        }

        $filename = $this->channelName() . '.log';
        $directory = $this->setting('directory', null);

        if (is_string($directory) && $directory !== '') {
            return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        }

        return storage_path('logs' . DIRECTORY_SEPARATOR . $filename);
    }

    /**
     * Setting precedence: per-package host config → global host config →
     * fluent LogDefinition → built-in default. (A host-defined
     * logging.channels entry short-circuits all of this in
     * resolveChannel().)
     */
    private function setting(string $key, mixed $default): mixed
    {
        if ($this->package->configVendor !== null) {
            $perPackage = config($this->package->getDottedNamespace() . ".logging.{$key}");
            if ($perPackage !== null) {
                return $perPackage;
            }
        }

        $global = config("laranail.package-tools.logging.{$key}");
        if ($global !== null) {
            return $global;
        }

        $definition = $this->package->getLogDefinition();
        if ($definition instanceof LogDefinition) {
            $fromDefinition = match ($key) {
                'enabled' => $definition->isEnabled(),
                'path' => $definition->pathValue(),
                'directory' => $definition->directoryValue(),
                'driver' => $definition->driverValue(),
                'days' => $definition->daysValue(),
                'level' => $definition->levelValue(),
                'channel' => $definition->channelValue(),
                'format' => $definition->formatValue(),
                'permission' => $definition->permissionValue(),
                default => null,
            };

            if ($fromDefinition !== null) {
                return $fromDefinition;
            }
        }

        return $default;
    }

    private function hasApp(): bool
    {
        return function_exists('app') && app()->bound('log') && app()->bound('config');
    }
}
