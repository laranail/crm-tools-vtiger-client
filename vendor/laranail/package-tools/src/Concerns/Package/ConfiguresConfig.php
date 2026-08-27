<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Config domain aggregator.
 */
trait ConfiguresConfig
{
    use HasAdditionalNamespaceFormats;
    use HasAdvancedConfig;
    use HasConfigDecorations;
    use HasConfigManipulation;
    use HasConfigNamespace;
    use HasConfigs;
    use HasGlobalConfigMerging;
    use HasNestedConfigFiles;
}
