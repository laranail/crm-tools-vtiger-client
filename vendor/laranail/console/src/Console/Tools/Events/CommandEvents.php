<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Events;

use Illuminate\Http\Request;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Console command lifecycle event.
 *
 * Self-contained (no dependency on the laranail core event base): carries
 * the action/type/metadata plus the command, input and exit code.
 */
class CommandEvents
{
    public ?string $action = null;

    public ?string $type = null;

    public ?Request $request = null;

    /** @var array<string, mixed> */
    public array $metadata = [];

    public readonly float $firedAt;

    public ?object $command = null;

    public ?InputInterface $input = null;

    public ?int $exitCode = null;

    public function __construct()
    {
        $this->firedAt = microtime(true);
    }

    public static function starting(object $command, InputInterface $input, ?Request $request = null, ?array $metadata = null): self
    {
        $event = new self;
        $event->command = $command;
        $event->input = $input;
        $event->populate('starting', 'command', $request, $metadata);

        return $event;
    }

    public static function terminating(object $command, InputInterface $input, int $exitCode, ?Request $request = null, ?array $metadata = null): self
    {
        $event = new self;
        $event->command = $command;
        $event->input = $input;
        $event->exitCode = $exitCode;
        $event->populate('terminating', 'command', $request, $metadata);

        return $event;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function populate(string $action, string $type, ?Request $request = null, ?array $metadata = null): void
    {
        $this->action = $action;
        $this->type = $type;
        $this->request = $request;
        $this->metadata = $metadata ?? [];
    }
}
