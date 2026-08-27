<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Services;

use Simtabi\Laranail\Console\Prompter\Exceptions\PrompterException;
use Simtabi\Laranail\Console\Prompter\Services\Components\ContextBuilderService;

/**
 * Dispatches any laravel/prompts helper by name.
 *
 * Every call forwards to the matching `\Laravel\Prompts\{method}()` function, so
 * the wrapper exposes the full prompts API (text, select, number, autocomplete,
 * datatable, grid, task, clear, note, …) and automatically tracks new helpers
 * upstream adds — no per-method map to maintain.
 */
class PromptService
{
    protected ContextBuilderService $contextBuilder;

    public function __construct()
    {
        $this->contextBuilder = new ContextBuilderService;
    }

    /**
     * Forward a call to the matching laravel/prompts helper function.
     *
     * @param array<int|string, mixed> $arguments
     *
     * @throws PrompterException If no such prompts helper exists.
     */
    public function __call(string $method, array $arguments): mixed
    {
        $function = 'Laravel\\Prompts\\' . $method;

        if (function_exists($function)) {
            return $function(...$arguments);
        }

        throw PrompterException::triggerErrorMessage('method_does_not_exist', ['method' => $method, 'class' => static::class]);
    }

    /**
     * Whether a method maps to a laravel/prompts helper function.
     */
    public function has(string $method): bool
    {
        return function_exists('Laravel\\Prompts\\' . $method);
    }

    /**
     * Provides access to context-related output methods.
     */
    public function context(): ContextBuilderService
    {
        return $this->contextBuilder;
    }
}
