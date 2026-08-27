<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use InvalidArgumentException;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition;

trait HasAboutSections
{
    /**
     * Legacy-form sections to surface under `php artisan about`.
     *
     * @var list<array{label: string, data: callable}>
     */
    public array $aboutSections = [];

    /** @var list<AboutSectionDefinition> */
    public array $aboutSectionDefinitions = [];

    /**
     * Add a section to Laravel's `about` command: a fluent
     * AboutSectionDefinition (per-field lazy closures, config gates), or
     * the legacy label + callable-returning-an-array form.
     */
    public function hasAboutSection(AboutSectionDefinition|string $label, ?callable $data = null): static
    {
        if ($label instanceof AboutSectionDefinition) {
            $this->aboutSectionDefinitions[] = $label;

            return $this;
        }

        if ($data === null) {
            throw new InvalidArgumentException('the string form of hasAboutSection() requires a callable; use AboutSectionDefinition::make() for the fluent form');
        }

        $this->aboutSections[] = [
            'label' => $label,
            'data' => $data,
        ];

        return $this;
    }

    /**
     * Register several `php artisan about` sections at once: definitions,
     * or label => callable pairs.
     *
     * @param array<int|string, AboutSectionDefinition|callable> $sections
     */
    public function hasAboutSections(array $sections): static
    {
        foreach ($sections as $label => $data) {
            if ($data instanceof AboutSectionDefinition) {
                $this->hasAboutSection($data);

                continue;
            }

            $this->hasAboutSection((string) $label, $data);
        }

        return $this;
    }
}
