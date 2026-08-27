<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;

trait HasBladeDirectives
{
    /** @var array<string, Closure> Map of directive name => handler */
    public array $bladeDirectives = [];

    /**
     * Register a single Blade directive
     *
     * @param string $name Directive name (e.g., 'icon')
     * @param Closure $handler Directive handler closure
     */
    public function hasBladeDirective(string $name, Closure $handler): static
    {
        $this->bladeDirectives[$name] = $handler;

        return $this;
    }

    /**
     * Register multiple Blade directives
     *
     * Accepts either:
     * - Array of ['name' => \Closure]
     * - Closure that returns array of ['name' => \Closure]
     *
     * @param array<string, Closure>|Closure $directives
     *
     * @example
     * $package->hasBladeDirectives([
     *     'icon' => fn($expression) => "<?php echo icon({$expression}); ?>",
     * ]);
     */
    public function hasBladeDirectives(array|Closure $directives): static
    {
        if ($directives instanceof Closure) {
            $directives = $directives();
        }

        foreach ($directives as $name => $handler) {
            $this->hasBladeDirective($name, $handler);
        }

        return $this;
    }
}
