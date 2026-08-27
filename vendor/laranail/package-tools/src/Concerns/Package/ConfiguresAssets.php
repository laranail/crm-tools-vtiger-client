<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Asset domain aggregator.
 *
 * The former HasAssetGroups (declarative group registry) and HasModuleAssets
 * (typed module conveniences) traits were folded into HasAssetPublisher to
 * resolve their collisions (`$assetGroups`, `getAssetGroups()`,
 * `publishModuleAssets()`); they no longer exist as standalone traits.
 */
trait ConfiguresAssets
{
    use HasAssetCleanup;
    use HasAssetPublisher;
    use HasAssets;
    use HasVueAssets;
}
