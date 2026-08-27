<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Package;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Runs Composer commands against a package directory.
 */
class ComposerService
{
    /**
     * Run composer install in package directory
     *
     * @param string $packagePath Path to package
     * @param bool $dev Include dev dependencies
     * @return array{success: bool, output: string}
     */
    public function install(string $packagePath, bool $dev = true): array
    {
        $command = ['composer', 'install'];

        if (! $dev) {
            $command[] = '--no-dev';
        }

        return $this->runCommand($command, $packagePath);
    }

    /**
     * Run composer update
     *
     * @param string $packagePath Path to package
     * @param string|null $package Specific package to update
     * @return array{success: bool, output: string}
     */
    public function update(string $packagePath, ?string $package = null): array
    {
        $command = ['composer', 'update'];

        if ($package) {
            $command[] = $package;
        }

        return $this->runCommand($command, $packagePath);
    }

    /**
     * Run composer require
     *
     * @param string $packagePath Path to package
     * @param string $package Package to require
     * @param bool $dev Add to require-dev
     * @return array{success: bool, output: string}
     */
    public function require(string $packagePath, string $package, bool $dev = false): array
    {
        $command = ['composer', 'require', $package];

        if ($dev) {
            $command[] = '--dev';
        }

        return $this->runCommand($command, $packagePath);
    }

    /**
     * Run composer remove
     *
     * @param string $packagePath Path to package
     * @param string $package Package to remove
     * @return array{success: bool, output: string}
     */
    public function remove(string $packagePath, string $package): array
    {
        $command = ['composer', 'remove', $package];

        return $this->runCommand($command, $packagePath);
    }

    /**
     * Run composer dump-autoload
     *
     * @param string $packagePath Path to package
     * @param bool $optimize Run with --optimize flag
     * @return array{success: bool, output: string}
     */
    public function dumpAutoload(string $packagePath, bool $optimize = false): array
    {
        $command = ['composer', 'dump-autoload'];

        if ($optimize) {
            $command[] = '--optimize';
        }

        return $this->runCommand($command, $packagePath);
    }

    /**
     * Validate composer.json
     *
     * @param string $packagePath Path to package
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(string $packagePath): array
    {
        $result = $this->runCommand(['composer', 'validate'], $packagePath);

        return [
            'valid' => $result['success'],
            'errors' => $result['success'] ? [] : [$result['output']],
        ];
    }

    /**
     * Get composer.json data
     *
     * @param string $packagePath Path to package
     * @return array<string, mixed>|null
     */
    public function getComposerData(string $packagePath): ?array
    {
        $composerPath = $packagePath . '/composer.json';

        if (! File::exists($composerPath)) {
            return null;
        }

        return json_decode(File::get($composerPath), true);
    }

    /**
     * Update composer.json
     *
     * @param string $packagePath Path to package
     * @param array<string, mixed> $data Data to update
     */
    public function updateComposerJson(string $packagePath, array $data): bool
    {
        $composerPath = $packagePath . '/composer.json';

        $current = $this->getComposerData($packagePath) ?? [];
        $merged = array_replace_recursive($current, $data);

        $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        return File::put($composerPath, $json) !== false;
    }

    /**
     * Run a composer command
     *
     * @param array<string> $command Command parts
     * @param string $workingDir Working directory
     * @return array{success: bool, output: string}
     */
    protected function runCommand(array $command, string $workingDir): array
    {
        $process = new Process($command, $workingDir);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput() ?: $process->getErrorOutput(),
        ];
    }
}
