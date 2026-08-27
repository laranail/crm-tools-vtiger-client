<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Services\Components;

use Simtabi\Laranail\Console\Prompter\Enums\ContextType;
use Simtabi\Laranail\Console\Prompter\Exceptions\PrompterException;

/**
 * Wraps Laravel Prompts context output (note/info/warning/...).
 *
 * This class manages context-related methods.
 *
 * @internal Used by the Prompter engine; reach it via `Console::prompter()`.
 *
 * @method void note(string $message)
 * @method void error(string $message)
 * @method void warning(string $message)
 * @method void alert(string $message)
 * @method void info(string $message)
 * @method void intro(string $message)
 * @method void outro(string $message)
 */
class ContextBuilderService
{
    /**
     * @var array<string, callable>
     */
    private array $contexts = [];

    /**
     * Constructor to initialize context methods.
     */
    public function __construct()
    {
        $this->initializeContexts();
    }

    /**
     * Initialize context methods based on ContextType enum.
     */
    private function initializeContexts(): void
    {
        foreach (ContextType::cases() as $type) {
            $this->contexts[$type->value] = $this->createContextMethod($type);
        }
    }

    /**
     * Create a context method.
     */
    private function createContextMethod(ContextType $type): callable
    {
        // Dispatch to the matching laravel/prompts helper so each type renders
        // correctly (warning/error/alert/info/intro/outro are distinct prompts,
        // not a Note with a "type" string).
        $function = '\\Laravel\\Prompts\\' . $type->value;

        return static function (string $message) use ($function): void {
            $function($message);
        };
    }

    /**
     * Magic method to dynamically call context methods.
     *
     * @param string $method The name of the method.
     * @param array $arguments The arguments to pass to the method.
     * @return mixed The result of the method call.
     *
     * @throws PrompterException If the context method does not exist.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (isset($this->contexts[$method])) {
            return $this->contexts[$method](...$arguments);
        }

        throw PrompterException::badMethodCall([
            'method' => $method,
            'methods' => rtrim(implode(', ', array_keys($this->contexts)), ', '),
        ]);
    }
}
