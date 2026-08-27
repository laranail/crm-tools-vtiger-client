<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Package\Tools\Support\Configurators\PaginatorConfigurator;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Declarative runtime tweaks a provider used to hand-write in its boot:
 * force HTTPS, set the app locale, and choose the pagination views. Every
 * value is resolved at boot (closures / `*FromConfig` variants), because a
 * value read at configure time predates the package's own config merge.
 */
trait HasRuntimeTweaks
{
    protected bool|Closure|null $useHttps = null;

    protected string|Closure|null $locale = null;

    protected ?string $paginationDefaultView = null;

    protected ?string $paginationSimpleView = null;

    /**
     * Force the HTTPS scheme on generated URLs when `$enabled` resolves
     * truthy at boot. Pass a Closure for deferred/complex resolution.
     */
    public function useHttps(bool|Closure $enabled = true): static
    {
        $this->useHttps = $enabled;

        return $this;
    }

    /**
     * Force HTTPS based on a config key, read at boot (after the package's
     * own config has merged).
     */
    public function useHttpsFromConfig(string $key, bool $default = true): static
    {
        $this->useHttps = static fn (): bool => (bool) config($key, $default);

        return $this;
    }

    /**
     * Set the application + Carbon locale. Pass a Closure returning the
     * locale string for deferred/complex resolution.
     */
    public function setLocale(string|Closure $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Set the locale from a config key, read at boot.
     */
    public function setLocaleFromConfig(string $key, string $default = 'en'): static
    {
        $this->locale = static fn (): string => (string) config($key, $default);

        return $this;
    }

    /**
     * Fluent pagination sub-builder (`->paginator()->setViews(…)`).
     */
    public function paginator(): PaginatorConfigurator
    {
        return new PaginatorConfigurator($this);
    }

    public function setPaginationViews(string $default, string $simple): static
    {
        $this->paginationDefaultView = $default;
        $this->paginationSimpleView = $simple;

        return $this;
    }

    public function setPaginationDefaultView(string $view): static
    {
        $this->paginationDefaultView = $view;

        return $this;
    }

    public function setPaginationSimpleView(string $view): static
    {
        $this->paginationSimpleView = $view;

        return $this;
    }

    /**
     * Apply all runtime tweaks. Call from the provider's boot(). `useHttps`
     * is Critical — swallowing it would serve HTTP when HTTPS was required (a
     * security misconfig); `setLocale` is Degradable — a wrong/default locale
     * is cosmetic and safe. Pagination views are plain strings.
     */
    public function bootPackageRuntimeTweaks(): void
    {
        FailurePolicy::run(function (): void {
            if ($this->resolveHttps()) {
                URL::forceScheme('https');
            }
        }, 'useHttps', BootCriticality::Critical);

        FailurePolicy::run(function (): void {
            $locale = $this->resolveLocale();
            if ($locale !== null && $locale !== '') {
                Carbon::setLocale($locale);
                App::setLocale($locale);
            }
        }, 'setLocale', BootCriticality::Degradable);

        if ($this->paginationDefaultView !== null) {
            Paginator::defaultView($this->paginationDefaultView);
        }
        if ($this->paginationSimpleView !== null) {
            Paginator::defaultSimpleView($this->paginationSimpleView);
        }
    }

    private function resolveHttps(): bool
    {
        if ($this->useHttps instanceof Closure) {
            return (bool) ($this->useHttps)();
        }

        return (bool) $this->useHttps;
    }

    private function resolveLocale(): ?string
    {
        if ($this->locale instanceof Closure) {
            return (string) ($this->locale)();
        }

        return $this->locale;
    }
}
