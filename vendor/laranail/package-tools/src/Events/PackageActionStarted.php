<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Events;

/**
 * Dispatched immediately before a package action runs (a seeder bundle, a
 * migration, a queued job, a scheduled task, an install step). The
 * cross-type counterpart to the seeder-specific PackageSeedingStarted —
 * see {@see PackageActionEvent} for the carried fields.
 */
final readonly class PackageActionStarted extends PackageActionEvent {}
