<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Simtabi\Laranail\Package\Tools\Services\Sbom\SbomGenerator;
use Throwable;

/**
 * `php artisan laranail::package-tools.sbom` generates a CycloneDX 1.5 JSON SBOM for the
 * current project. Pure-PHP, reads composer.json + composer.lock.
 */
final class PackageSbomCommand extends Command
{
    protected $signature = 'laranail::package-tools.sbom
        {--output=sbom.json : Output path (relative to project root or absolute)}
        {--print : Print SBOM JSON to stdout instead of writing a file}';

    protected $description = 'Generate a CycloneDX SBOM (laranail/package-tools).';

    public function handle(): int
    {
        $generator = new SbomGenerator(base_path());

        try {
            if ($this->option('print')) {
                $this->line(json_encode(
                    $generator->generate(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ));

                return self::SUCCESS;
            }

            $output = $this->stringOption('output');
            $written = $generator->generateToFile($output);

            $this->info("CycloneDX SBOM written to: {$written}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('SBOM generation failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
