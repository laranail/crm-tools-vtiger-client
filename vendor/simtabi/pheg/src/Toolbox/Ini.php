<?php

namespace Simtabi\Pheg\Toolbox;

class Ini
{

    public function __construct() {}

    public function iniSet(string $key, int|string|null $value): static
    {
        @ini_set($key, $value);

        return $this;
    }

    public function maximumExecutionTimeAndMemoryLimit(): static
    {
        $this->iniSet('max_execution_time', -1);
        $this->iniSet('memory_limit', -1);

        return $this;
    }

}