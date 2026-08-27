<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Configurators;

use Simtabi\Laranail\Package\Tools\Concerns\Package\HasRuntimeTweaks;

/**
 * Fluent pagination view sub-builder, returned by `$package->paginator()`.
 * Values are stored on the package and applied at boot via
 * {@see HasRuntimeTweaks::bootPackageRuntimeTweaks()}.
 */
final class PaginatorConfigurator extends PackageConfigurator
{
    public function setViews(string $default, string $simple): self
    {
        $this->package->setPaginationViews($default, $simple);

        return $this;
    }

    public function defaultView(string $view): self
    {
        $this->package->setPaginationDefaultView($view);

        return $this;
    }

    public function simpleView(string $view): self
    {
        $this->package->setPaginationSimpleView($view);

        return $this;
    }

    public function useTailwind(): self
    {
        return $this->setViews('pagination::tailwind', 'pagination::simple-tailwind');
    }

    public function useBootstrap(): self
    {
        return $this->setViews('pagination::bootstrap-5', 'pagination::simple-bootstrap-5');
    }
}
