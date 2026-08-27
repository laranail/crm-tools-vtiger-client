<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Sbom;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Support\Path\Path;

/**
 * Pure-PHP CycloneDX 1.5 JSON SBOM generator.
 *
 * Reads the host project's `composer.json` and `composer.lock`, emits a
 * CycloneDX SBOM (spec 1.5, JSON serialisation). No shelling out: every
 * byte is computed in-process, so the output is reproducible.
 *
 * Reference: https://cyclonedx.org/docs/1.5/json/
 */
final readonly class SbomGenerator
{
    public function __construct(private string $projectRoot) {}

    /**
     * Produce the CycloneDX SBOM as a PHP array (ready to json_encode).
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $composerJson = $this->readComposerJson();
        $composerLock = $this->readComposerLock();

        $rootName = $composerJson['name'] ?? 'application';
        $rootVersion = $composerJson['version'] ?? ($composerLock['version'] ?? 'dev');

        return [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.5',
            'version' => 1,
            'serialNumber' => 'urn:uuid:' . $this->uuid(),
            'metadata' => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'tools' => [[
                    'vendor' => 'simtabi',
                    'name' => 'laranail/package-tools',
                    'version' => $this->toolVersion(),
                ]],
                'component' => $this->buildComponent($rootName, $rootVersion, 'application', $composerJson['description'] ?? null),
            ],
            'components' => $this->buildPackageComponents($composerLock),
        ];
    }

    /**
     * Generate and write the SBOM JSON to disk. Returns the absolute path
     * written.
     *
     * The output path is clamped to within `$projectRoot`; symlinks and
     * `..` traversal that would escape the project tree are refused. To
     * write outside the project, run a separate `cp` step.
     */
    public function generateToFile(string $outputPath): string
    {
        $json = json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $abs = $this->resolveOutputPath($outputPath);
        $dir = dirname($abs);
        if (! File::isDirectory($dir)) {
            File::ensureDirectoryExists($dir, 0o755);
        }

        File::put($abs, $json . "\n");

        return $abs;
    }

    /**
     * Resolve the operator-supplied output path, refusing anything that
     * would write outside `$projectRoot`. Lexical normalisation only;
     * the file is not required to exist yet.
     */
    private function resolveOutputPath(string $outputPath): string
    {
        $candidate = $this->isAbsolute($outputPath)
            ? $outputPath
            : Path::join($this->projectRoot, $outputPath);

        $normalised = $this->lexicalNormalise($candidate);
        $rootNormalised = $this->lexicalNormalise($this->projectRoot);

        if (! Path::isWithin($rootNormalised, $normalised)) {
            throw new RuntimeException(
                "SBOM output path is outside project root: {$outputPath}"
            );
        }

        return $normalised;
    }

    /**
     * Collapse `.` and `..` segments without touching the filesystem.
     */
    private function lexicalNormalise(string $path): string
    {
        $absolute = $this->isAbsolute($path);
        $segments = [];

        // Split on either separator. Splitting on '/' alone left a Windows path as a single segment,
        // so '..' was never seen and the containment check below compared two unsplit strings.
        foreach (Path::segments($path) as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return ($absolute ? Path::SEPARATOR : '') . implode(Path::SEPARATOR, $segments);
    }

    /**
     * A leading separator, or a Windows drive letter. Str::startsWith($path, '/') recognises neither
     * a backslash root nor 'C:\', so it read an absolute Windows path as relative and joined it onto
     * the project root.
     */
    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[a-z]:#i', $path) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerJson(): array
    {
        return $this->readJsonFile($this->projectRoot . '/composer.json', 'composer.json');
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerLock(): array
    {
        return $this->readJsonFile(
            $this->projectRoot . '/composer.lock',
            'composer.lock',
            'Run `composer install` first.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path, string $label, string $hint = ''): array
    {
        if (! File::isFile($path)) {
            $hint = $hint === '' ? '' : ' ' . $hint;
            throw new RuntimeException("{$label} not found at: {$path}.{$hint}");
        }

        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException("{$label} is not a JSON object: {$path}");
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $lock
     * @return list<array<string, mixed>>
     */
    private function buildPackageComponents(array $lock): array
    {
        $components = [];

        foreach ((array) Arr::get($lock, 'packages', []) as $pkg) {
            $components[] = $this->buildComponentFromLockEntry($pkg);
        }

        foreach ((array) Arr::get($lock, 'packages-dev', []) as $pkg) {
            $component = $this->buildComponentFromLockEntry($pkg);
            $component['scope'] = 'optional';
            $components[] = $component;
        }

        return $components;
    }

    /**
     * @param array<string, mixed> $pkg
     * @return array<string, mixed>
     */
    private function buildComponentFromLockEntry(array $pkg): array
    {
        $rawVersion = (string) Arr::get($pkg, 'version', 'dev');
        $licenses = array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            (array) Arr::get($pkg, 'license', []),
        ));

        return $this->buildComponent(
            name: (string) Arr::get($pkg, 'name', 'unknown'),
            version: Str::startsWith($rawVersion, 'v') ? Str::after($rawVersion, 'v') : $rawVersion,
            type: 'library',
            description: Arr::get($pkg, 'description'),
            homepage: Arr::get($pkg, 'homepage'),
            licenses: $licenses,
        );
    }

    /**
     * @param list<string> $licenses
     * @return array<string, mixed>
     */
    private function buildComponent(
        string $name,
        string $version,
        string $type,
        ?string $description = null,
        ?string $homepage = null,
        array $licenses = [],
    ): array {
        $component = [
            'type' => $type,
            'bom-ref' => "pkg:composer/{$name}@{$version}",
            'name' => $name,
            'version' => $version,
            'purl' => "pkg:composer/{$name}@{$version}",
        ];

        if ($description !== null && $description !== '') {
            $component['description'] = $description;
        }

        if ($homepage !== null && $homepage !== '') {
            $component['externalReferences'] = [[
                'type' => 'website',
                'url' => $homepage,
            ]];
        }

        if ($licenses !== []) {
            $component['licenses'] = array_map(
                static fn (string $id): array => ['license' => ['id' => $id]],
                $licenses,
            );
        }

        return $component;
    }

    /**
     * RFC 4122 v4 UUID for the SBOM serial number. Cryptographically random.
     */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private function toolVersion(): string
    {
        $composerJsonPath = dirname(__DIR__, 3) . '/composer.json';
        if (! File::isFile($composerJsonPath)) {
            return 'dev';
        }

        $data = json_decode(File::get($composerJsonPath), true);

        return is_array($data) ? ($data['version'] ?? 'dev') : 'dev';
    }
}
