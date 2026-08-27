<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Attributes;

use Attribute;

/**
 * Marks a contract / interface as having an auto-generated facade.
 *
 * Use with `Package::discoversWithAttributes()` and `FacadeAutoGenerator`.
 * The Package builder creates a `Facade`-extending class and registers the
 * alias.
 *
 * Example:
 * ```
 * #[AsFacade(alias: 'Foo')]
 * interface FooContract { … }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsFacade
{
    public function __construct(
        public string $alias,
        public ?string $accessor = null,
    ) {}
}
