<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Events\PackageActionFailed;
use Simtabi\Laranail\Package\Tools\Events\PackageActionStarted;
use Simtabi\Laranail\Package\Tools\Events\PackageActionSucceeded;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;
use Simtabi\Laranail\Package\Tools\Services\Log\PackageLogger;
use Throwable;

/**
 * Global, use-anywhere access to the {@see PackageActionReporter}. Report or
 * observe any package action — from a service provider, a command, a job, or
 * plain application code — without wiring anything up.
 *
 * @method static PackageActionReporter forPackage(?PackageLogger $logger)
 * @method static PackageActionStarted starting(PackageActionStarted $event)
 * @method static PackageActionSucceeded succeeded(PackageActionSucceeded $event)
 * @method static PackageActionFailed report(PackageActionFailed $event)
 * @method static PackageActionStarted started(PackageActionType $type, string $action, ?string $packageName = null, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionSucceeded success(PackageActionType $type, string $action, ?string $packageName = null, ?float $durationMs = null, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionFailed fail(PackageActionType $type, string $action, ?string $packageName, string $message, FailureReason $reason = FailureReason::Failed, ?string $exceptionClass = null, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionFailed fromThrowable(PackageActionType $type, string $action, ?string $packageName, Throwable $e, ?FailureReason $reason = null, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionFailed interrupted(PackageActionType $type, string $action, ?string $packageName, string $message, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionFailed cancelled(PackageActionType $type, string $action, ?string $packageName, string $message, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionFailed timedOut(PackageActionType $type, string $action, ?string $packageName, string $message, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionFailed unknown(PackageActionType $type, string $action, ?string $packageName, string $message, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static mixed track(PackageActionType $type, string $action, ?string $packageName, Closure $work, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionStarted migrationStarting(string $migration, string $direction = 'up', array $context = [])
 * @method static PackageActionSucceeded migrationSucceeded(string $migration, string $direction = 'up', ?float $durationMs = null, array $context = [])
 * @method static PackageActionFailed migrationFailed(string $migration, string $direction, Throwable $e, array $context = [])
 * @method static PackageActionFailed seederFailed(string $action, ?string $packageName, Throwable $e, ?FailureReason $reason = null, array $context = [], ?SeederExecutionMode $mode = null)
 * @method static PackageActionFailed jobFailed(string $action, Throwable $e, ?FailureReason $reason = null, array $context = [], ?SeederExecutionMode $mode = null)
 *
 * @see PackageActionReporter
 */
final class PackageActions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PackageActionReporter::class;
    }
}
