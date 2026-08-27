<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

/**
 * something that can express itself as a 5-field cron expression. the
 * explicit terminal method (laravel's Arrayable/Jsonable pattern) beats
 * __toString magic for type-safety and discoverability; schedulers depend
 * on this abstraction, not on any concrete builder.
 */
interface CronExpressible
{
    /**
     * a valid 5-field cron expression, e.g. '0 2 * * 1-5'.
     */
    public function toExpression(): string;
}
