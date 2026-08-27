<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Livewire;

use Illuminate\Support\Facades\Facade;

/**
 * @method static LivewireServiceInterface registerComponent(string $alias, string $class)
 * @method static LivewireServiceInterface registerComponents(array<string, string> $map)
 * @method static array<string, string>    getRegisteredComponents()
 * @method static void                     flush()
 * @method static bool                     isLivewireAvailable()
 * @method static string                   generateComponentKey(string $name)
 *
 * @see LivewireService
 */
class Livewire extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LivewireServiceInterface::class;
    }
}
