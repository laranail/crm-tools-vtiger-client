<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Notifications\Contracts;

/**
 * Minimal contract for a console output channel — standalone, with no
 * dependency on any host notification framework.
 */
interface ConsoleChannelInterface
{
    /**
     * The channel identifier.
     */
    public function getName(): string;

    /**
     * Write a message to the console.
     *
     * @param array<string, mixed> $data
     */
    public function send(string $message, array $data = []): bool;
}
