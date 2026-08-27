<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

/**
 * Base contract implemented by all service classes.
 */
interface ServiceInterface
{
    /**
     * Whether the service is initialized and ready to use.
     */
    public function isReady(): bool;

    public function getName(): string;
}
