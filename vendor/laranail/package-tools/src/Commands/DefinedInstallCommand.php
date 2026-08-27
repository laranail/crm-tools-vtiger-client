<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Override;
use Simtabi\Laranail\Package\Tools\Enums\PackageActionType;
use Simtabi\Laranail\Package\Tools\Facades\PackageActions;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Support\Definitions\InstallCommandDefinition;
use Throwable;

/**
 * The install command built from an InstallCommandDefinition: steps run in
 * declaration order — built-ins and custom steps interleave freely, unlike
 * the legacy fixed pipeline.
 */
final class DefinedInstallCommand extends InstallCommand
{
    public function __construct(
        Package $package,
        private readonly InstallCommandDefinition $definition,
    ) {
        parent::__construct(
            $package,
            $definition->signature(),
            $definition->isHidden(),
        );
    }

    #[Override]
    public function handle(): int
    {
        $reporter = PackageActions::forPackage($this->package->log());
        $name = $this->package->shortName();

        $reporter->started(PackageActionType::Install, $name, $this->package->name);
        $start = microtime(true);

        // steps are deliberately not wrapped for control flow: an install
        // step that throws should abort the run loudly (artisan renders the
        // exception and exits non-zero) rather than half-install quietly —
        // same contract as the legacy fixed pipeline. We only observe the
        // failure (reporting it) then rethrow, preserving that contract.
        try {
            foreach ($this->definition->steps() as $step) {
                ($step['run'])($this);
            }
        } catch (Throwable $e) {
            $reporter->fromThrowable(PackageActionType::Install, $name, $this->package->name, $e);

            throw $e;
        }

        $reporter->success(PackageActionType::Install, $name, $this->package->name, (microtime(true) - $start) * 1000);

        $this->info("{$this->package->shortName()} has been installed!");

        return self::SUCCESS;
    }
}
