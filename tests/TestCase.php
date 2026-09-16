<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Tests;

use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;
use Simtabi\Laranail\Toolkit\Providers\ToolkitServiceProvider;
use Simtabi\Laranail\CrmTools\VtigerClient\Providers\VtWsClientServiceProvider;

abstract class TestCase extends IsolatedTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ToolkitServiceProvider::class,
            VtWsClientServiceProvider::class,
        ];
    }
}
