<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor\Checks;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Throwable;

/**
 * Asserts one or more paths are writable (creating them if missing), with an
 * optional minimum free-disk-space warning.
 */
final readonly class WritablePathCheck implements DoctorCheck
{
    /** @param array<string, string>|list<string> $paths label => path, or a plain list (paths label themselves) */
    public function __construct(
        private array $paths,
        private ?int $minFreeBytes = null,
        private ?string $name = null,
        private ?string $description = null,
    ) {}

    public function name(): string
    {
        return $this->name ?? 'fs:writable';
    }

    public function description(): string
    {
        return $this->description ?? 'Required paths are writable';
    }

    public function run(): DoctorResult
    {
        $notWritable = [];

        foreach ($this->paths as $label => $path) {
            $label = is_string($label) ? $label : $path;

            if (! File::isDirectory($path)) {
                try {
                    File::ensureDirectoryExists($path);
                } catch (Throwable) {
                    // fall through to the writability check below
                }
            }

            $probe = File::isDirectory($path) ? $path : dirname($path);

            if (! File::isWritable($probe)) {
                $notWritable[$label] = $path;
            }
        }

        if ($notWritable !== []) {
            return DoctorResult::fail('Path is not writable.', $notWritable);
        }

        if ($this->minFreeBytes !== null && $this->paths !== []) {
            $first = (string) (array_values($this->paths)[0] ?? '');
            $dir = File::isDirectory($first) ? $first : dirname($first);
            $free = (int) (@disk_free_space($dir) ?: 0);

            if ($free > 0 && $free < $this->minFreeBytes) {
                return DoctorResult::warn('Low free disk space.', ['free_bytes' => $free]);
            }
        }

        return DoctorResult::pass('Writable' . ($this->minFreeBytes !== null ? '; sufficient disk space' : '') . '.');
    }
}
