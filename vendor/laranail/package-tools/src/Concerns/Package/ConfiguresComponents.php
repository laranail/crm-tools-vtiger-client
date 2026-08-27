<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Component domain aggregator.
 */
trait ConfiguresComponents
{
    use HasBladeComponents;
    use HasBladeDirectives;
    use HasComponentNamespaces;
    use HasEnhancedAnonymousComponents;
    use HasInertia;
    use HasLivewireComponents;
    use HasSafeComponentRegistration;
    use HasViewComponentLoader;
    use HasVueComponents;
}
