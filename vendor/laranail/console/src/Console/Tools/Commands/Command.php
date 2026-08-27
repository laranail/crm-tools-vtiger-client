<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands;

use Illuminate\Console\Command as BaseCommand;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleServices;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleWriter;

/**
 * Enhanced Artisan command base. Wraps {@see BaseCommand::run()} with a managed
 * lifecycle — performance timing, event dispatch, signal handling, structured
 * logging and exception capture — coordinated by a
 * {@see CommandServiceManager}.
 *
 * This base is intentionally thin: it owns the lifecycle and a few verbosity
 * helpers, and exposes everything else through `$this->services` (its nine
 * discrete services), e.g. `$this->services->metadata()->add(...)`,
 * `$this->services->interaction()->askText(...)`,
 * `$this->services->performance()->getExecutionTime()`. Extend it when you want
 * the full lifecycle; for a lightweight prompter command use
 * `Prompter\Commands\AbstractPrompterCommand` instead.
 *
 * All of that behaviour lives in {@see InteractsWithConsoleServices} — `use` that
 * trait directly when you must extend a different command base.
 *
 * @see https://laravel.com/docs/artisan
 *
 * @api Stable command base (SemVer-covered).
 */
abstract class Command extends BaseCommand
{
    use InteractsWithConsoleServices;
    use InteractsWithConsoleWriter;

    /**
     * Convenience aliases applied after construction — e.g. a bare `make:crud`
     * alongside the namespaced `laranail::<package-slug>.<command>` name.
     *
     * Aliases are written through whatever `setAliases()` is in scope, so a
     * command that also `use`s {@see Concerns\SupportsNamespacedNames} may list
     * `::`-namespaced aliases here; otherwise standard Symfony validation applies.
     *
     * @var list<string>
     */
    protected array $commandAliases = [];

    public function __construct()
    {
        parent::__construct();

        // Eagerly boot so $this->services is available immediately after
        // construction (the trait also boots lazily on run() for trait-only use).
        $this->bootConsoleSupport();

        if ($this->commandAliases !== []) {
            $this->setAliases($this->commandAliases);
        }
    }
}
