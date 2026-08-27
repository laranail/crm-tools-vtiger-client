<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * Test helper for verifying that a PUBLISHED namespaced config override reaches
 * its dotted config key.
 *
 * The override bridge (`ProcessConfigs::mergePublishedConfigOverride()`) runs in
 * the provider's REGISTER phase, so the published file must already exist when
 * the provider registers. Writing it in Testbench's `getEnvironmentSetUp()`
 * races that order and is flaky; this helper instead writes the file and then
 * registers a FRESH provider instance, which is deterministic.
 *
 * Mix into any Testbench/Orchestra test case:
 *
 * ```php
 * use AssertsPublishedConfigOverrides;
 *
 * $this->assertPublishedConfigOverride(
 *     MyServiceProvider::class, 'acme.widget',
 *     ['enabled' => false], 'acme.widget.enabled', false,
 * );
 * ```
 */
trait AssertsPublishedConfigOverrides
{
    /**
     * Write a published override for $configKey, register a fresh instance of
     * $providerClass, and assert the override reached config($assertKey).
     *
     * @param class-string<PackageServiceProvider> $providerClass
     * @param string $configKey Dotted namespaced key, e.g. 'acme.widget'.
     * @param array<string, mixed> $override Values written to the published file.
     * @param string $assertKey Dotted key to read after the override is applied.
     * @param mixed $expected Expected value at $assertKey.
     */
    protected function assertPublishedConfigOverride(
        string $providerClass,
        string $configKey,
        array $override,
        string $assertKey,
        mixed $expected
    ): void {
        $published = config_path(str_replace('.', '/', $configKey) . '.php');

        try {
            File::ensureDirectoryExists(dirname($published));
            File::put($published, '<?php return ' . var_export($override, true) . ';' . PHP_EOL);

            (new $providerClass(app()))->register();

            Assert::assertSame($expected, config($assertKey), sprintf(
                'Published override at %s did not reach config(%s).',
                $published,
                $assertKey,
            ));
        } finally {
            if (File::exists($published)) {
                File::delete($published);
            }

            @rmdir(dirname($published));
        }
    }
}
