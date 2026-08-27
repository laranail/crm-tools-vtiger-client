<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

/**
 * Laravel's built-in queue connections, for enum-first fluent APIs
 * (`->onQueue('seeding', connection: QueueConnection::Redis)`). Signatures
 * that accept this also accept any BackedEnum or raw string, so custom
 * host connections keep working.
 *
 * The 'null' connection's case is named None — PHP forbids null/true/false
 * as enum-case names.
 */
enum QueueConnection: string
{
    case Sync = 'sync';
    case Database = 'database';
    case Redis = 'redis';
    case Sqs = 'sqs';
    case Beanstalkd = 'beanstalkd';
    case None = 'null';
}
