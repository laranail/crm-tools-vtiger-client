<?php

namespace Livewire;

/**
 * Minimal PHPStan stub for the optional livewire/livewire dependency.
 *
 * The Livewire module references {@see Livewire::component()} only behind a
 * runtime `class_exists()` guard, so the package never hard-depends on
 * livewire/livewire. This stub gives the analyser the single symbol it needs
 * to type-check the guarded call without installing the package.
 */
class Livewire
{
    /**
     * @param class-string|string $class
     */
    public static function component(string $alias, string $class): void {}
}
