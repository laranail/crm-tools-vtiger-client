<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Attributes;

use Attribute;

/**
 * Marks a class as a discoverable view composer.
 *
 * Use with `Package::discoversWithAttributes()`. The Package builder
 * registers the class as a Laravel view composer for the specified view(s).
 *
 * Example:
 * ```
 * #[AsViewComposer(views: ['layouts.app', 'partials.header'])]
 * final class AppViewComposer { public function compose(View $view) { … } }
 *
 * // Single view also accepted:
 * #[AsViewComposer(views: 'layouts.app')]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsViewComposer
{
    public function __construct(
        /** @var string|string[] */
        public string|array $views,
    ) {}
}
