<?php declare(strict_types=1);

namespace USIPCOM\VtWsClient\Facades;

use Illuminate\Support\Facades\Facade;
use USIPCOM\VtWsClient\VtWsClient;

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
