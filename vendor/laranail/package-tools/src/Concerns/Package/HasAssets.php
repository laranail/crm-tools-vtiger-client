<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasAssets
{
    public bool $hasAssets = false;

    public function hasAssets(): static
    {
        $this->hasAssets = true;

        return $this;
    }
}
