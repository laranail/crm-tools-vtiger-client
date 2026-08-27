<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasViewSharedData
{
    /** @var array<string, mixed> Map of name => shared value */
    public array $sharedViewData = [];

    /**
     * Share data with all views. Pass a single `name`/`value` pair, or an
     * associative `name => value` array as the first argument to share several
     * at once.
     *
     * @param string|array<string, mixed> $name
     * @param mixed $value
     */
    public function sharesDataWithAllViews(string|array $name, $value = null): static
    {
        if (is_array($name)) {
            foreach ($name as $key => $val) {
                $this->sharedViewData[$key] = $val;
            }

            return $this;
        }

        $this->sharedViewData[$name] = $value;

        return $this;
    }
}
