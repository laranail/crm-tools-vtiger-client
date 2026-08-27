<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Simtabi\Laranail\Package\Tools\Support\Registry\PackageRegistry;

/**
 * `php artisan laranail::package-tools.packages` — every package built on this toolkit, what each one
 * claimed, and whether any two claimed the same name.
 *
 * The last part is the reason it exists. Laravel keeps view namespaces, translation namespaces,
 * config keys and publish tags in flat global maps, so a second package claiming a key does not
 * collide loudly — it silently replaces the first, and the failure surfaces far away as a missing
 * view or the wrong file published. Nothing in the framework will tell you it happened; this will.
 */
final class PackagesCommand extends Command
{
    protected $signature = 'laranail::package-tools.packages
        {package? : Show everything known about one package, by composer name}
        {--json : Emit JSON instead of TTY output}
        {--detail : Show every package in full rather than as a summary table}
        {--collisions : Report only the clashes, and exit non-zero if there are any}';

    protected $description = 'List packages built on laranail/package-tools and detect name clashes.';

    public function handle(PackageRegistry $registry): int
    {
        $described = array_map($registry->describe(...), array_keys($registry->all()));
        usort($described, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        $collisions = $registry->collisions();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['packages' => $described, 'collisions' => $collisions],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return $collisions === [] ? self::SUCCESS : self::FAILURE;
        }

        $wanted = $this->argument('package');

        if (is_string($wanted) && $wanted !== '') {
            $match = array_values(array_filter($described, static fn (array $p): bool => $p['name'] === $wanted));

            if ($match === []) {
                $this->error(sprintf('No registered package named "%s".', $wanted));
                $this->line('  Run without an argument to list what is registered.');

                return self::FAILURE;
            }

            $this->renderDetail($match[0]);

            return self::SUCCESS;
        }

        if (! $this->option('collisions')) {
            $this->option('detail')
                ? array_walk($described, fn (array $p): null => $this->renderDetail($p))
                : $this->renderPackages($described);
        }

        $this->renderCollisions($collisions);

        // Only --collisions makes a clash fatal. A plain listing is a listing: failing it would make
        // the command unusable for the thing people run it for most.
        return $this->option('collisions') && $collisions !== [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $described
     */
    private function renderPackages(array $described): void
    {
        if ($described === []) {
            $this->warn('No packages built on laranail/package-tools are registered.');

            return;
        }

        $this->table(
            ['Package', 'Version', 'What it does', 'Cmds', 'Config'],
            array_map(fn (array $p): array => [
                $p['name'],
                $this->versionLabel($p),
                // Truncated, not wrapped: a summary table stops being scannable the moment one row
                // is four lines tall. `--detail` and the single-package view show it whole.
                $this->truncate($p['description'] ?? '—', 52),
                (string) count($p['commands']),
                $p['config'] ?? '—',
            ], $described),
        );

        $this->line(sprintf(
            '  %d package(s). Add a name for one in full, or --detail for all.',
            count($described),
        ));
    }

    /**
     * Everything known about one package.
     *
     * Aligned label/value lines rather than a table: these values are prose, URLs and lists of wildly
     * different lengths, and a bordered table sized to the longest of them spends most of the
     * terminal on whitespace.
     *
     * @param array<string, mixed> $package
     */
    private function renderDetail(array $package): void
    {
        $this->newLine();
        $this->line(sprintf('  <options=bold>%s</>  <fg=gray>%s</>', $package['name'], $this->versionLabel($package)));

        if (is_string($package['description']) && $package['description'] !== '') {
            $this->line(sprintf('  %s', $package['description']));
        }

        $this->newLine();

        $rows = [
            'Authors' => implode(', ', $package['authors']),
            'License' => $package['license'],
            'Docs' => $package['docs'],
            'Keywords' => implode(', ', $package['keywords']),
            'Provider' => $package['provider'],
            'Path' => $package['path'],
            'Config key' => $package['config'],
            'Views' => $package['views'],
            'Translations' => $package['translations'],
            'Components' => $package['components'],
            'Publish tags' => implode(', ', $package['publishTags']),
            'Commands' => implode(', ', $package['commands']),
        ];

        foreach ($rows as $label => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $this->line(sprintf('  <fg=gray>%s</>  %s', str_pad($label . ':', 15), $value));
        }
    }

    /**
     * @param array<string, mixed> $package
     */
    private function versionLabel(array $package): string
    {
        $version = is_string($package['version']) ? $package['version'] : 'unknown';

        return is_string($package['stability']) && $package['stability'] !== ''
            ? sprintf('%s (%s)', $version, $package['stability'])
            : $version;
    }

    /** Truncated, not wrapped: a summary table stops being scannable once a row is four lines tall. */
    private function truncate(string $value, int $width): string
    {
        return mb_strlen($value) <= $width ? $value : mb_substr($value, 0, $width - 1) . '…';
    }

    /**
     * @param array<string, array<string, list<string>>> $collisions
     */
    private function renderCollisions(array $collisions): void
    {
        if ($collisions === []) {
            $this->info('No name clashes: every config key, view namespace, translation namespace and component prefix is claimed once.');

            return;
        }

        $this->newLine();
        $this->error('Name clashes found. The later package silently replaces the earlier one.');

        foreach ($collisions as $surface => $byName) {
            foreach ($byName as $name => $packages) {
                $this->line(sprintf('  %s  <fg=yellow>%s</>  claimed by: %s', $surface, $name, implode(', ', $packages)));
            }
        }
    }
}
