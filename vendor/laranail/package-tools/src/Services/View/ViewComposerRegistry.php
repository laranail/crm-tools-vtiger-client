<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\View;

use Closure;
use Illuminate\Support\Facades\View;
use Simtabi\Laranail\Package\Tools\Contracts\RegistryInterface;

/**
 * Registers view composers for data injection.
 */
class ViewComposerRegistry implements RegistryInterface
{
    /** @var array<string, array<int, string|callable>> */
    protected array $composers = [];

    /** @var array<string, mixed> */
    protected array $sharedData = [];

    /**
     * Register a view composer
     *
     * @param array<int, string>|string $views View name(s)
     * @param string|callable $composer Composer class or callable
     */
    public function registerComposer(array|string $views, string|callable $composer): void
    {
        $views = is_array($views) ? $views : [$views];

        // View::composer() is typed Closure|string; wrap any other callable
        // (array/invokable) in a Closure so the call type-checks and behaves
        // identically at runtime.
        View::composer($views, is_string($composer) ? $composer : Closure::fromCallable($composer));

        foreach ($views as $view) {
            if (! isset($this->composers[$view])) {
                $this->composers[$view] = [];
            }
            $this->composers[$view][] = $composer;
        }
    }

    /**
     * Register a view creator
     *
     * @param array<int, string>|string $views View name(s)
     * @param string|callable $creator Creator class or callable
     */
    public function registerCreator(array|string $views, string|callable $creator): void
    {
        $views = is_array($views) ? $views : [$views];
        View::creator($views, is_string($creator) ? $creator : Closure::fromCallable($creator));
    }

    /**
     * Share data with all views
     *
     * @param string $key Data key
     * @param mixed $value Data value
     */
    public function share(string $key, mixed $value): void
    {
        View::share($key, $value);
        $this->sharedData[$key] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function register(string $key, mixed $value): void
    {
        $this->registerComposer($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function getRegistered(): array
    {
        return [
            'composers' => $this->composers,
            'shared' => $this->sharedData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset($this->composers[$key]) || isset($this->sharedData[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->composers[$key] ?? $this->sharedData[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(string $key): void
    {
        unset($this->composers[$key], $this->sharedData[$key]);
    }
}
