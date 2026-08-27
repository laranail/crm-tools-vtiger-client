<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Attributes;

use Attribute;

/**
 * Marks a class (typically an invokable controller) as a discoverable route.
 *
 * Use with `Package::discoversWithAttributes()`. Repeatable so a single
 * controller can register multiple routes.
 *
 * Example:
 * ```
 * #[AsRoute(method: 'GET',  uri: '/foo')]
 * #[AsRoute(method: 'POST', uri: '/foo', name: 'foo.create')]
 * final class FooController { public function __invoke() { … } }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsRoute
{
    public function __construct(
        public string $method,
        public string $uri,
        public ?string $name = null,
        /** @var string[] */
        public array $middleware = [],
    ) {}
}
