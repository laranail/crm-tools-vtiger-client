<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Attributes;

use Attribute;

/**
 * Marks a class as a discoverable Artisan command.
 *
 * Use with `Package::discoversWithAttributes()`. The Package builder scans
 * src/ for classes carrying this attribute and registers them via
 * `hasCommand()`.
 *
 * Example:
 * ```
 * #[AsArtisanCommand(signature: 'foo:run', description: 'Run the foo task')]
 * final class FooRunCommand extends Command { … }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsArtisanCommand
{
    public function __construct(
        public string $signature,
        public ?string $description = null,
    ) {}
}
