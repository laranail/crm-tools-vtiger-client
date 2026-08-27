<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

use Override;
use Simtabi\Laranail\Package\Tools\Enums\FailureReason;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;
use Simtabi\Laranail\Package\Tools\Services\Event\PackageActionReporter;
use Throwable;

/**
 * The one general failure event for any package action — migrations,
 * seeders, jobs, schedules, installs, or custom work. Serialization-safe:
 * the originating exception is captured as its class name + message, never
 * as a live Throwable, so listeners may queue.
 *
 * `$reason` carries the failure taxonomy ({@see FailureReason}); consult
 * the seeder-specific PackageSeedingFailed for the seeder detail layer.
 */
final readonly class PackageActionFailed extends PackageActionEvent
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        PackageActionType $type,
        public FailureReason $reason,
        string $action,
        ?string $packageName = null,
        public ?string $exceptionClass = null,
        public string $message = '',
        array $context = [],
        ?SeederExecutionMode $mode = null,
    ) {
        parent::__construct($type, $action, $packageName, $context, $mode);
    }

    /**
     * Build from a throwable with an explicitly-supplied reason, lifting the
     * exception's class/message/file/line into the event. The reason is
     * passed in (not derived) — {@see PackageActionReporter}
     * is the one caller that computes it via {@see FailureReason::fromThrowable()}.
     *
     * @param array<string, mixed> $context
     */
    public static function fromThrowable(
        PackageActionType $type,
        FailureReason $reason,
        string $action,
        ?string $packageName,
        Throwable $e,
        array $context = [],
        ?SeederExecutionMode $mode = null,
    ): self {
        return new self(
            type: $type,
            reason: $reason,
            action: $action,
            packageName: $packageName,
            exceptionClass: $e::class,
            message: $e->getMessage(),
            context: [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                ...$context,
            ],
            mode: $mode,
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'reason' => $this->reason->value,
            'exception' => $this->exceptionClass,
            'message' => $this->message,
        ];
    }
}
