<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use BackedEnum;
use Closure;
use InvalidArgumentException;
use Simtabi\Laranail\Package\Tools\Contracts\CronExpressible;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\TimeOfDay;

/**
 * a generic deferred-call recorder: capture fluent calls now, replay them
 * on a real target later. target-agnostic by design — the scheduler uses
 * it for Event calls, and any future deferred-proxy need can reuse it.
 *
 * one explicit argument normalizer runs at replay so typed values flow
 * through magic dispatch uniformly: BackedEnum -> value, TimeOfDay ->
 * format24(), CronExpressible -> toExpression().
 */
final class DeferredCallQueue
{
    /** @var list<array{method: string, args: array<int, mixed>}|Closure> */
    private array $calls = [];

    public function record(string $method, array $args): void
    {
        $this->calls[] = ['method' => $method, 'args' => $args];
    }

    public function recordClosure(Closure $callback): void
    {
        $this->calls[] = $callback;
    }

    public function isEmpty(): bool
    {
        return $this->calls === [];
    }

    /**
     * @return list<array{method: string, args: array<int, mixed>}|Closure>
     */
    public function all(): array
    {
        return $this->calls;
    }

    public function replayOn(object $target): void
    {
        foreach ($this->calls as $call) {
            if ($call instanceof Closure) {
                $call($target);

                continue;
            }

            if (! method_exists($target, $call['method'])) {
                throw new InvalidArgumentException(sprintf(
                    'deferred call "%s" does not exist on %s',
                    $call['method'],
                    $target::class,
                ));
            }

            $target->{$call['method']}(...array_map($this->normalize(...), $call['args']));
        }
    }

    private function normalize(mixed $arg): mixed
    {
        return match (true) {
            // recurse into arrays so environments([Environment::Production])
            // normalizes the same as the variadic form
            is_array($arg) => array_map($this->normalize(...), $arg),
            $arg instanceof BackedEnum => $arg->value,
            $arg instanceof TimeOfDay => $arg->format24(),
            $arg instanceof CronExpressible => $arg->toExpression(),
            default => $arg,
        };
    }
}
