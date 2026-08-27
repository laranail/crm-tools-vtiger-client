<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

use Override;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;

/**
 * Dispatched after a package action completes without failure. Carries the
 * optional wall-clock `$durationMs` alongside the shared
 * {@see PackageActionEvent} fields.
 */
final readonly class PackageActionSucceeded extends PackageActionEvent
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        PackageActionType $type,
        string $action,
        ?string $packageName = null,
        public ?float $durationMs = null,
        array $context = [],
        ?SeederExecutionMode $mode = null,
    ) {
        parent::__construct($type, $action, $packageName, $context, $mode);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'duration_ms' => $this->durationMs,
        ];
    }
}
