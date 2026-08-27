<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Enums\SeederExecutionMode;

/**
 * Shared, serialization-safe shape for the package-action lifecycle family
 * ({@see PackageActionStarted}, {@see PackageActionSucceeded},
 * {@see PackageActionFailed}). Holds only scalars/arrays/enums — never a
 * live Throwable — so listeners may queue.
 *
 * `$action` names the unit of work (a bundle key, a migration name, a job
 * label); `$packageName` attributes it to a package/namespace when known;
 * `$context` carries free-form structured detail; `$mode` records how a
 * seeder run was initiated (null for non-seeder actions).
 */
abstract readonly class PackageActionEvent
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public PackageActionType $type,
        public string $action,
        public ?string $packageName = null,
        public array $context = [],
        public ?SeederExecutionMode $mode = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'action' => $this->action,
            'package' => $this->packageName,
            'context' => $this->context,
            'mode' => $this->mode?->value,
        ];
    }
}
