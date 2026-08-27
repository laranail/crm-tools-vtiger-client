<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Adds/removes composer repositories and installs/uninstalls packages.
 *
 * @api Consumer-facing extension trait — `use`-d by external package authors.
 */
trait ManagesComposer
{
    protected bool $printComposerErrors = false;

    protected int $composerTimeout = 300;

    public function withComposerErrors(bool $print = true): static
    {
        $this->printComposerErrors = $print;

        return $this;
    }

    /**
     * @param int $timeout Timeout in seconds
     */
    public function withComposerTimeout(int $timeout): static
    {
        $this->composerTimeout = $timeout;

        return $this;
    }

    /**
     * Add package repository to composer.json
     */
    public function addComposerRepository(
        string $vendor,
        string $package,
        string $path,
        bool $symlink = true
    ): bool {
        $vendorKebab = Str::kebab($vendor);
        $packageKebab = Str::kebab($package);
        $repositoryName = "{$vendorKebab}/{$packageKebab}";

        $config = [
            'type' => 'path',
            'url' => $path,
        ];

        if ($symlink) {
            $config['options'] = ['symlink' => true];
        }

        $process = new Process([
            'composer',
            'config',
            "repositories.{$repositoryName}",
            json_encode($config),
            '--file',
            'composer.json',
        ]);

        return $this->runComposerProcess($process);
    }

    /**
     * Remove package repository from composer.json
     */
    public function removeComposerRepository(string $vendor, string $package): bool
    {
        $vendorKebab = Str::kebab($vendor);
        $packageKebab = Str::kebab($package);
        $repositoryName = "{$vendorKebab}/{$packageKebab}";

        $process = new Process([
            'composer',
            'config',
            '--unset',
            "repositories.{$repositoryName}",
        ]);

        return $this->runComposerProcess($process);
    }

    /**
     * Require a package via composer
     */
    public function composerRequire(
        string $vendor,
        string $package,
        string $version = '*@dev'
    ): bool {
        $vendorKebab = Str::kebab($vendor);
        $packageKebab = Str::kebab($package);
        $packageName = "{$vendorKebab}/{$packageKebab}";

        $process = new Process([
            'composer',
            'require',
            "{$packageName}:{$version}",
        ]);

        return $this->runComposerProcess($process);
    }

    /**
     * @param bool $removeRepository Whether to remove repository entry
     */
    public function composerRemove(string $vendor, string $package, bool $removeRepository = true): bool
    {
        $vendorKebab = Str::kebab($vendor);
        $packageKebab = Str::kebab($package);
        $packageName = "{$vendorKebab}/{$packageKebab}";

        $process = new Process([
            'composer',
            'remove',
            $packageName,
        ]);

        $success = $this->runComposerProcess($process);

        if ($success && $removeRepository) {
            $this->removeComposerRepository($vendor, $package);
        }

        return $success;
    }

    /**
     * Dump composer autoload
     */
    public function composerDumpAutoload(): bool
    {
        $process = new Process(['composer', 'dump-autoload']);

        return $this->runComposerProcess($process);
    }

    /**
     * Install a local package (add repository and require)
     */
    public function installLocalPackage(
        string $vendor,
        string $package,
        string $path,
        string $version = '*@dev'
    ): bool {
        if (! $this->addComposerRepository($vendor, $package, $path)) {
            return false;
        }

        if (! $this->composerRequire($vendor, $package, $version)) {
            // Roll back the repository we just added.
            $this->removeComposerRepository($vendor, $package);

            return false;
        }

        return true;
    }

    /**
     * Uninstall a local package (remove and remove repository)
     */
    public function uninstallLocalPackage(string $vendor, string $package): bool
    {
        if (! $this->composerRemove($vendor, $package)) {
            return false;
        }

        return $this->removeComposerRepository($vendor, $package);
    }

    /**
     * Run a composer process
     *
     * @throws ProcessFailedException
     */
    protected function runComposerProcess(Process $process): bool
    {
        $process->setTimeout($this->composerTimeout);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if ($process->isSuccessful() || $process->getExitCode() === 0) {
            return true;
        }

        if ($this->printComposerErrors) {
            throw new ProcessFailedException($process);
        }

        return false;
    }

    /**
     * Check if composer is available
     */
    public function isComposerAvailable(): bool
    {
        $process = new Process(['composer', '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Get composer version
     */
    public function getComposerVersion(): ?string
    {
        $process = new Process(['composer', '--version', '--no-ansi']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return null;
    }

    /**
     * Get package status (enabled, disabled, or not_found)
     */
    public function getPackageStatus(string $vendor, string $package): string
    {
        $vendorKebab = Str::kebab($vendor);
        $packageKebab = Str::kebab($package);
        $composerName = "{$vendorKebab}/{$packageKebab}";

        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            return 'not_found';
        }

        $composer = json_decode(File::get($composerPath), true);

        if (isset($composer['require'][$composerName])) {
            return 'enabled';
        }

        // Commented-out lines count as disabled.
        $composerContent = File::get($composerPath);
        $pattern = '/\/\/\s*"' . preg_quote($composerName, '/') . '"/';

        if (preg_match($pattern, $composerContent)) {
            return 'disabled';
        }

        if (isset($composer['require-dev'][$composerName])) {
            return 'enabled';
        }

        return 'not_found';
    }

    /**
     * Enable a disabled package
     */
    public function enablePackage(string $vendor, string $package): bool
    {
        $vendorKebab = Str::kebab($vendor);
        $packageKebab = Str::kebab($package);
        $composerName = "{$vendorKebab}/{$packageKebab}";

        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            return false;
        }

        $composerContent = File::get($composerPath);

        // Uncomment the package line.
        $pattern = '/\/\/\s*("' . preg_quote($composerName, '/') . '"\s*:\s*"[^"]+",?)/';
        $replacement = '$1';

        $newContent = preg_replace($pattern, $replacement, $composerContent);

        if ($newContent === $composerContent) {
            // Not present as a commented line. Require it if a matching path
            // repository exists.
            $composer = json_decode($composerContent, true);

            $hasRepository = false;
            if (isset($composer['repositories'])) {
                foreach ($composer['repositories'] as $repo) {
                    if (isset($repo['type']) && $repo['type'] === 'path') {
                        $repoPath = base_path($repo['url']);
                        if (File::exists($repoPath . '/composer.json')) {
                            $pkgComposer = json_decode(File::get($repoPath . '/composer.json'), true);
                            if (isset($pkgComposer['name']) && $pkgComposer['name'] === $composerName) {
                                $hasRepository = true;
                                break;
                            }
                        }
                    }
                }
            }

            if (! $hasRepository) {
                return false;
            }

            return $this->composerRequire($vendor, $package);
        }

        File::put($composerPath, $newContent);

        return true;
    }

    /**
     * Disable a package (comment it out in composer.json)
     */
    public function disablePackage(string $vendor, string $package): bool
    {
        $vendorKebab = Str::kebab($vendor);
        $packageKebab = Str::kebab($package);
        $composerName = "{$vendorKebab}/{$packageKebab}";

        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            return false;
        }

        $composerContent = File::get($composerPath);

        // Comment out the package line.
        $pattern = '/("' . preg_quote($composerName, '/') . '"\s*:\s*"[^"]+",?)/';
        $replacement = '// $1';

        $newContent = preg_replace($pattern, $replacement, $composerContent);

        if ($newContent === $composerContent) {
            return false;
        }

        File::put($composerPath, $newContent);

        return true;
    }
}
