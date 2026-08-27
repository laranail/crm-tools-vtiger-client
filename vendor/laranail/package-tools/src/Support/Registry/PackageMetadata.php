<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Registry;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Support\Path\Path;
use Throwable;

/**
 * What a package says about itself, read from its own `composer.json`.
 *
 * Description, authors, homepage, licence and keywords are already declared there, and a package
 * author has to keep that file correct to publish at all. Asking for them again through the fluent
 * builder would be a second copy of the same facts, free to drift from the one composer enforces —
 * so this reads them, and the builder only overrides where a package genuinely wants to say
 * something different at runtime.
 */
final class PackageMetadata
{
    /** @var array<string, array{description: ?string, authors: list<string>, homepage: ?string, license: ?string, keywords: list<string>, docs: ?string}> */
    private static array $cache = [];

    /**
     * @return array{description: ?string, authors: list<string>, homepage: ?string, license: ?string, keywords: list<string>, docs: ?string}
     */
    public static function for(string $package): array
    {
        return self::$cache[$package] ??= self::read($package);
    }

    /**
     * @return array{description: ?string, authors: list<string>, homepage: ?string, license: ?string, keywords: list<string>, docs: ?string}
     */
    private static function read(string $package): array
    {
        $empty = [
            'description' => null,
            'authors' => [],
            'homepage' => null,
            'license' => null,
            'keywords' => [],
            'docs' => null,
        ];

        $manifest = self::manifestPath($package);

        if ($manifest === null || ! File::isFile($manifest)) {
            return $empty;
        }

        try {
            /** @var array<string, mixed> $json */
            $json = json_decode((string) File::get($manifest), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            // A malformed manifest is the package's problem, not a reason for this report to fail.
            return $empty;
        }

        return [
            'description' => self::string($json['description'] ?? null),
            'authors' => self::authors($json['authors'] ?? null),
            'homepage' => self::string($json['homepage'] ?? null),
            'license' => self::license($json['license'] ?? null),
            'keywords' => array_values(array_filter(
                is_array($json['keywords'] ?? null) ? $json['keywords'] : [],
                static fn (mixed $k): bool => is_string($k) && $k !== '',
            )),
            'docs' => self::string(
                is_array($json['support'] ?? null) ? ($json['support']['docs'] ?? null) : null,
            ),
        ];
    }

    /**
     * Composer's own install path, falling back to nothing rather than guessing: a path repository
     * or a package loaded outside composer has no manifest to find, and inventing one would report
     * a neighbouring package's metadata as this one's.
     */
    private static function manifestPath(string $package): ?string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($package)) {
            return null;
        }

        $root = InstalledVersions::getInstallPath($package);

        return $root === null ? null : Path::join($root, 'composer.json');
    }

    /**
     * @return list<string>
     */
    private static function authors(mixed $authors): array
    {
        if (! is_array($authors)) {
            return [];
        }

        $names = [];

        foreach ($authors as $author) {
            if (is_array($author) && is_string($author['name'] ?? null) && $author['name'] !== '') {
                $names[] = $author['name'];
            } elseif (is_string($author) && $author !== '') {
                $names[] = $author;
            }
        }

        return $names;
    }

    /** Composer allows a string or a list; a disjunctive licence is rendered as it is declared. */
    private static function license(mixed $license): ?string
    {
        if (is_string($license)) {
            return $license === '' ? null : $license;
        }

        if (is_array($license)) {
            $parts = array_values(array_filter($license, static fn (mixed $l): bool => is_string($l) && $l !== ''));

            return $parts === [] ? null : implode(' OR ', $parts);
        }

        return null;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Test seam: the cache is static, and a test that installs a fake package needs it cleared. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
