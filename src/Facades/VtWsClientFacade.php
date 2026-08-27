<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient;

class VtWsClientFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return VtWsClient::class;
    }
}
