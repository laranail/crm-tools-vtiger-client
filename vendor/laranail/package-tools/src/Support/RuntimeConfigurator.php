<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use Barryvdh\Debugbar\Facades\Debugbar;
use Clockwork\Support\Laravel\ClockworkServiceProvider;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;

/**
 * Chainable API for adjusting PHP runtime settings during heavy
 * operations: memory limits, execution time, INI settings, and disabling
 * debugging tools (Telescope, Xdebug, Clockwork, Debugbar). Use scope()
 * to apply settings for the duration of a callback and restore them after.
 *
 *     RuntimeConfigurator::make()
 *         ->memory('2G')
 *         ->timeout(0)
 *         ->apply();
 *
 * @api
 */
final class RuntimeConfigurator
{
    /** @var array<string, mixed> Original values for restoration */
    private array $originalValues = [];

    /** @var array<string, mixed> Pending INI changes to apply */
    private array $pending = [];

    /** @var array<string, bool> Debugging tools to disable */
    private array $disableTools = [
        'telescope' => false,
        'xdebug' => false,
        'clockwork' => false,
        'debugbar' => false,
    ];

    /** @var bool Whether to log configuration changes */
    private bool $logging = false;

    /** @var string|null Log channel to use */
    private ?string $logChannel = null;

    /** @var bool Whether changes have been applied */
    private bool $applied = false;

    public function __construct()
    {
        $this->captureOriginalValues();
    }

    public static function make(): static
    {
        return new self;
    }

    /**
     * Pre-configured for queue jobs: 1G memory, no timeout, Telescope disabled.
     */
    public static function forQueueJob(): static
    {
        return self::make()
            ->memory('1G')
            ->timeout(0)
            ->disableTelescope();
    }

    /**
     * Pre-configured for heavy batch operations: 2G memory, no timeout, all debugging off.
     */
    public static function forBatchProcessing(): static
    {
        return self::make()
            ->memory('2G')
            ->timeout(0)
            ->disableAllDebugging();
    }

    /**
     * Pre-configured for imports: 1G memory, 30-minute timeout, Telescope disabled.
     */
    public static function forImport(): static
    {
        return self::make()
            ->memory('1G')
            ->timeoutMinutes(30)
            ->disableTelescope();
    }

    /**
     * Pre-configured for exports: 1G memory, 15-minute timeout, Telescope disabled.
     */
    public static function forExport(): static
    {
        return self::make()
            ->memory('1G')
            ->timeoutMinutes(15)
            ->disableTelescope();
    }

    // Memory.

    public function memory(string $limit): static
    {
        $this->pending['memory_limit'] = $limit;

        return $this;
    }

    public function memoryMb(int $megabytes): static
    {
        return $this->memory("{$megabytes}M");
    }

    public function memoryGb(int|float $gigabytes): static
    {
        return $this->memory(((int) ($gigabytes * 1024)) . 'M');
    }

    public function unlimitedMemory(): static
    {
        return $this->memory('-1');
    }

    // Execution time.

    public function timeout(int $seconds): static
    {
        $this->pending['max_execution_time'] = $seconds;

        return $this;
    }

    public function maxExecutionTime(int $seconds): static
    {
        return $this->timeout($seconds);
    }

    public function noTimeout(): static
    {
        return $this->timeout(0);
    }

    public function timeoutMinutes(int $minutes): static
    {
        return $this->timeout($minutes * 60);
    }

    public function timeoutHours(int $hours): static
    {
        return $this->timeout($hours * 3600);
    }

    // Error reporting.

    public function errorReporting(int $level): static
    {
        $this->pending['error_reporting'] = $level;

        return $this;
    }

    public function reportAllErrors(): static
    {
        return $this->errorReporting(E_ALL);
    }

    public function reportErrorsOnly(): static
    {
        return $this->errorReporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
    }

    public function suppressErrors(): static
    {
        return $this->errorReporting(0);
    }

    public function displayErrors(bool $display = true): static
    {
        $this->pending['display_errors'] = $display ? '1' : '0';

        return $this;
    }

    // Debugging tools.

    public function disableTelescope(): static
    {
        $this->disableTools['telescope'] = true;

        return $this;
    }

    public function enableTelescope(): static
    {
        $this->disableTools['telescope'] = false;

        return $this;
    }

    public function disableXdebug(): static
    {
        $this->disableTools['xdebug'] = true;

        return $this;
    }

    public function enableXdebug(): static
    {
        $this->disableTools['xdebug'] = false;

        return $this;
    }

    public function disableClockwork(): static
    {
        $this->disableTools['clockwork'] = true;

        return $this;
    }

    public function enableClockwork(): static
    {
        $this->disableTools['clockwork'] = false;

        return $this;
    }

    public function disableDebugbar(): static
    {
        $this->disableTools['debugbar'] = true;

        return $this;
    }

    public function enableDebugbar(): static
    {
        $this->disableTools['debugbar'] = false;

        return $this;
    }

    public function disableAllDebugging(): static
    {
        return $this
            ->disableTelescope()
            ->disableXdebug()
            ->disableClockwork()
            ->disableDebugbar();
    }

    // INI settings.

    public function set(string $key, mixed $value): static
    {
        $this->pending[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function setMany(array $settings): static
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function realpathCacheSize(string $size): static
    {
        return $this->set('realpath_cache_size', $size);
    }

    public function realpathCacheTtl(int $seconds): static
    {
        return $this->set('realpath_cache_ttl', $seconds);
    }

    public function postMaxSize(string $size): static
    {
        return $this->set('post_max_size', $size);
    }

    public function uploadMaxFilesize(string $size): static
    {
        return $this->set('upload_max_filesize', $size);
    }

    public function forLargeUploads(string $maxSize = '100M'): static
    {
        return $this
            ->postMaxSize($maxSize)
            ->uploadMaxFilesize($maxSize)
            ->memory('512M')
            ->timeoutMinutes(10);
    }

    // Conditional.

    public function when(bool $condition, callable $callback, ?callable $else = null): static
    {
        if ($condition) {
            $callback($this);
        } elseif ($else !== null) {
            $else($this);
        }

        return $this;
    }

    public function unless(bool $condition, callable $callback): static
    {
        return $this->when(! $condition, $callback);
    }

    public function whenCli(callable $callback): static
    {
        return $this->when(self::isCli(), $callback);
    }

    public function whenWeb(callable $callback): static
    {
        return $this->when(! self::isCli(), $callback);
    }

    // Logging.

    public function withLogging(?string $channel = null): static
    {
        $this->logging = true;
        $this->logChannel = $channel;

        return $this;
    }

    public function withoutLogging(): static
    {
        $this->logging = false;

        return $this;
    }

    // Apply / restore / scope.

    public function apply(): static
    {
        foreach ($this->pending as $key => $value) {
            $this->applyIniSetting($key, $value);
        }

        $this->applyDebuggingToolSettings();
        $this->applied = true;

        if ($this->logging) {
            $this->logChanges('Applied runtime configuration');
        }

        return $this;
    }

    public function scope(callable $callback): mixed
    {
        $this->apply();

        try {
            return $callback();
        } finally {
            $this->restore();
        }
    }

    public function restore(): static
    {
        foreach ($this->originalValues as $key => $value) {
            if ($value !== false) {
                @ini_set($key, (string) $value);
            }
        }

        $this->restoreDebuggingTools();
        $this->applied = false;

        if ($this->logging) {
            $this->logChanges('Restored runtime configuration');
        }

        return $this;
    }

    // Accessors.

    public function isApplied(): bool
    {
        return $this->applied;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPending(): array
    {
        return $this->pending;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginal(): array
    {
        return $this->originalValues;
    }

    /**
     * @return array<string, bool>
     */
    public function getDisabledTools(): array
    {
        return array_filter($this->disableTools);
    }

    public function get(string $key): string|false
    {
        return ini_get($key);
    }

    // Static helpers.

    public static function setMemory(string $limit): void
    {
        self::make()->memory($limit)->apply();
    }

    public static function setTimeout(int $seconds): void
    {
        self::make()->timeout($seconds)->apply();
    }

    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    public static function hasTelescopeInstalled(): bool
    {
        return class_exists(Telescope::class);
    }

    public static function hasXdebugLoaded(): bool
    {
        return extension_loaded('xdebug');
    }

    public static function hasDebugbarInstalled(): bool
    {
        return class_exists(Debugbar::class);
    }

    public static function hasClockworkInstalled(): bool
    {
        return class_exists(ClockworkServiceProvider::class);
    }

    public static function getMemoryUsage(): string
    {
        return self::formatBytes(memory_get_usage(true));
    }

    public static function getPeakMemoryUsage(): string
    {
        return self::formatBytes(memory_get_peak_usage(true));
    }

    public static function getMemoryLimit(): string
    {
        return ini_get('memory_limit') ?: '-1';
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = (int) floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // Internal.

    private function captureOriginalValues(): void
    {
        foreach ([
            'memory_limit', 'max_execution_time', 'error_reporting',
            'display_errors', 'realpath_cache_size', 'realpath_cache_ttl',
            'post_max_size', 'upload_max_filesize',
        ] as $key) {
            $this->originalValues[$key] = ini_get($key);
        }
    }

    private function applyIniSetting(string $key, mixed $value): void
    {
        if (! isset($this->originalValues[$key])) {
            $this->originalValues[$key] = ini_get($key);
        }

        if ($key === 'max_execution_time' && function_exists('set_time_limit')) {
            @set_time_limit((int) $value);

            return;
        }

        if ($key === 'error_reporting' && function_exists('error_reporting')) {
            error_reporting((int) $value);

            return;
        }

        if (function_exists('ini_set')) {
            @ini_set($key, (string) $value);
        }
    }

    private function applyDebuggingToolSettings(): void
    {
        if ($this->disableTools['telescope'] && class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        if ($this->disableTools['xdebug'] && extension_loaded('xdebug')) {
            if (function_exists('xdebug_disable')) {
                @xdebug_disable();
            }
            @ini_set('xdebug.mode', 'off');
        }

        if ($this->disableTools['debugbar'] && class_exists(Debugbar::class)) {
            Debugbar::disable();
        }

        if ($this->disableTools['clockwork'] && class_exists(ClockworkServiceProvider::class)) {
            config(['clockwork.enable' => false]);
        }
    }

    private function restoreDebuggingTools(): void
    {
        if ($this->disableTools['telescope'] && class_exists(Telescope::class)) {
            Telescope::startRecording();
        }

        if ($this->disableTools['debugbar'] && class_exists(Debugbar::class)) {
            Debugbar::enable();
        }

        if ($this->disableTools['clockwork'] && class_exists(ClockworkServiceProvider::class)) {
            config(['clockwork.enable' => true]);
        }
    }

    private function logChanges(string $message): void
    {
        $context = [
            'pending' => $this->pending,
            'disabled_tools' => array_filter($this->disableTools),
            'memory_usage' => self::getMemoryUsage(),
        ];

        if ($this->logChannel) {
            Log::channel($this->logChannel)->debug($message, $context);
        } else {
            Log::debug($message, $context);
        }
    }
}
