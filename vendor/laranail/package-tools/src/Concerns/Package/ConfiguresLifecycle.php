<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Lifecycle domain aggregator.
 *
 * Hosts the `registeringPackage`, `packageRegistered`, `bootingPackage`,
 * `packageBooted` hooks consumers override, plus boot-time discovery, batch
 * loading, doctor checks, logging, and validation.
 */
trait ConfiguresLifecycle
{
    use DiscoversWithAttributes;
    use HasAbout;
    use HasAboutSections;
    use HasBatchResourceLoading;
    use HasDoctorChecks;
    use HasEnhancedValidation;
    use HasLifecycleHooks;
    use HasLogging;
    use HasRuntimeTweaks;
    use HasValidationRules;
}
