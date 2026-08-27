<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Package;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Simtabi\Laranail\Package\Tools\Contracts\AnalyzerInterface;

/**
 * Analyzes package structure, complexity, and metrics.
 */
class PackageAnalyzer implements AnalyzerInterface
{
    /**
     * Analyze package
     *
     * @param string $packagePath Path to package
     * @return array<string, mixed> Analysis results
     */
    public function analyze(string $packagePath): array
    {
        return [
            'structure' => $this->analyzeStructure($packagePath),
            'metrics' => $this->calculateMetrics($packagePath),
            'files' => $this->countFiles($packagePath),
            'composer' => $this->analyzeComposerJson($packagePath),
        ];
    }

    /**
     * Analyze package structure
     *
     * @param string $packagePath Package path
     * @return array<string, bool>
     */
    protected function analyzeStructure(string $packagePath): array
    {
        return [
            'has_src' => File::isDirectory($packagePath . '/src'),
            'has_tests' => File::isDirectory($packagePath . '/tests'),
            'has_config' => File::isDirectory($packagePath . '/config'),
            'has_resources' => File::isDirectory($packagePath . '/resources'),
            'has_routes' => File::isDirectory($packagePath . '/routes'),
            'has_database' => File::isDirectory($packagePath . '/database'),
            'has_composer' => File::exists($packagePath . '/composer.json'),
            'has_readme' => File::exists($packagePath . '/README.md'),
            'has_license' => File::exists($packagePath . '/LICENSE') || File::exists($packagePath . '/LICENSE.md'),
        ];
    }

    /**
     * Calculate package metrics
     *
     * @param string $packagePath Package path
     * @return array<string, int>
     */
    protected function calculateMetrics(string $packagePath): array
    {
        $srcPath = $packagePath . '/src';

        if (! File::isDirectory($srcPath)) {
            return [
                'lines_of_code' => 0,
                'classes' => 0,
                'methods' => 0,
            ];
        }

        $loc = 0;
        $classes = 0;
        $methods = 0;

        $files = File::allFiles($srcPath);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $content = File::get($file->getPathname());
                $loc += count(explode("\n", $content));
                $classes += substr_count($content, 'class ');
                $classes += substr_count($content, 'interface ');
                $classes += substr_count($content, 'trait ');
                $methods += substr_count($content, 'function ');
            }
        }

        return [
            'lines_of_code' => $loc,
            'classes' => $classes,
            'methods' => $methods,
        ];
    }

    /**
     * Count files by type
     *
     * @param string $packagePath Package path
     * @return array<string, int>
     */
    protected function countFiles(string $packagePath): array
    {
        $counts = [
            'php' => 0,
            'blade' => 0,
            'js' => 0,
            'css' => 0,
        ];

        if (File::isDirectory($packagePath)) {
            $files = File::allFiles($packagePath);

            foreach ($files as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $counts['blade']++;

                    continue;
                }

                $ext = $file->getExtension();
                if (isset($counts[$ext])) {
                    $counts[$ext]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Analyze composer.json
     *
     * @param string $packagePath Package path
     * @return array<string, mixed>
     */
    protected function analyzeComposerJson(string $packagePath): array
    {
        $composerPath = $packagePath . '/composer.json';

        if (! File::exists($composerPath)) {
            return [];
        }

        $composer = json_decode(File::get($composerPath), true);

        return [
            'name' => $composer['name'] ?? null,
            'type' => $composer['type'] ?? null,
            'license' => $composer['license'] ?? null,
            'dependencies_count' => count($composer['require'] ?? []),
            'dev_dependencies_count' => count($composer['require-dev'] ?? []),
        ];
    }

    /**
     * Render an analysis result. `json` is the canonical machine format;
     * `text` is a flat key/value listing for terminal viewing.
     *
     * @param array<string, mixed> $findings
     */
    public function getReport(array $findings, string $format = 'json'): string
    {
        return match ($format) {
            'json' => json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
            'text' => $this->renderText($findings),
            default => throw new InvalidArgumentException("Unsupported report format: {$format}"),
        };
    }

    /**
     * @param array<string, mixed> $findings
     */
    private function renderText(array $findings, string $prefix = ''): string
    {
        $lines = [];
        foreach ($findings as $key => $value) {
            $label = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $lines[] = $this->renderText($value, $label);

                continue;
            }
            $rendered = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) || $value === null ? (string) $value : json_encode($value));
            $lines[] = "{$label} = {$rendered}";
        }

        return implode("\n", array_filter($lines, static fn (string $l): bool => $l !== ''));
    }
}
