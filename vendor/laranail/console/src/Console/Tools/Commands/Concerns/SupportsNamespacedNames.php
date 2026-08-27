<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Concerns;

use ReflectionProperty;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * Lets a command use the laranail naming shape `laranail::<package-slug>.<command>`.
 *
 * Symfony's {@see SymfonyCommand::validateName()} rejects the empty segment in `::`,
 * so this trait sets the name (and aliases) past that validator by writing the
 * private property directly. Dispatch still works because Symfony resolves an exact
 * command name before its `:`-splitting namespace lookup.
 *
 * @api Stable extension point (SemVer-covered).
 */
trait SupportsNamespacedNames
{
    public function setName(string $name): static
    {
        $this->writeName('name', $name);

        return $this;
    }

    /**
     * @param list<string> $aliases
     */
    public function setAliases(iterable $aliases): static
    {
        $this->writeName('aliases', is_array($aliases) ? $aliases : iterator_to_array($aliases));

        return $this;
    }

    private function writeName(string $property, mixed $value): void
    {
        // The properties are private on Symfony's base Command.
        new ReflectionProperty(SymfonyCommand::class, $property)->setValue($this, $value);
    }
}
