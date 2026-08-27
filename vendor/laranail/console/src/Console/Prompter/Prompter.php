<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter;

use Closure;
use Illuminate\Support\Collection;

use function Laravel\Prompts\form;

use Laravel\Prompts\FormBuilder;
use Simtabi\Laranail\Console\Prompter\Exceptions\PrompterException;
use Simtabi\Laranail\Console\Prompter\Services\Components\ContextBuilderService;
use Simtabi\Laranail\Console\Prompter\Services\PromptService;

/**
 * Class Prompter
 *
 * This class provides a fluent interface for chaining prompt method calls.
 *
 * @method static self text(string $label, string $placeholder = '', string $default = '', bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static self textarea(string $label, string $placeholder = '', string $default = '', bool|string $required = false, ?Closure $validate = null, string $hint = '', int $rows = 5)
 * @method static self password(string $label, string $placeholder = '', bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static self select(string $label, array|Collection $options, int|string|null $default = null, int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true)
 * @method static self multiselect(string $label, array|Collection $options, array|Collection $default = [], int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = 'Use the space bar to select options.')
 * @method static self confirm(string $label, bool $default = true, string $yes = 'Yes', string $no = 'No', bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static self pause(string $message = 'Press enter to continue...')
 * @method static self suggest(string $label, array|Collection|Closure $options, string $placeholder = '', string $default = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static self search(string $label, Closure $options, string $placeholder = '', int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true)
 * @method static self multisearch(string $label, Closure $options, string $placeholder = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = 'Use the space bar to select options.')
 * @method static self spin(Closure $callback, string $message = '')
 * @method static self table(array|Collection $headers = [], array|Collection|null $rows = null)
 * @method static self progress(string $label, iterable|int $steps, ?Closure $callback = null, string $hint = '')
 * @method static self number(string $label, string $placeholder = '', int|string $default = '', ?int $min = null, ?int $max = null, int $step = 1, bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static self autocomplete(string $label, array|Collection|Closure $options, string $placeholder = '', string $default = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = '', ?int $matches = null)
 * @method static self clear()
 * @method static self note(string $message, ?string $type = null)
 * @method static self info(string $message)
 * @method static self warning(string $message)
 * @method static self error(string $message)
 * @method static self alert(string $message)
 * @method static self intro(string $message)
 * @method static self outro(string $message)
 * @method static self title(string $message)
 * @method static self notify(string $title, string $message)
 * @method static self task(string $label, ?Closure $callback = null, string $hint = '')
 * @method static self datatable(array|Collection $columns = [], array|Collection|null $rows = null)
 * @method static self grid(array|Collection $items = [], int $columns = 3)
 * @method static self stream(iterable|Closure $stream)
 * @method FormBuilder form()
 */
class Prompter
{
    protected PromptService $promptManager;

    protected mixed $result = null;

    /**
     * Prompter constructor.
     */
    private function __construct()
    {
        $this->promptManager = new PromptService;
    }

    /**
     * Create a fresh Prompter: each fluent chain gets its own $result, so
     * sequential/concurrent (e.g. Octane) callers never clobber one another's
     * last value.
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Magic method to dynamically call prompt methods.
     *
     * @param string $method The name of the method.
     * @param array $arguments The arguments to pass to the method.
     *
     * @throws PrompterException If the method does not exist.
     */
    public function __call(string $method, array $arguments): self
    {
        // has() resolves any laravel/prompts helper (prompts + context output:
        // number/clear/datatable/grid/task/note/…), so the wrapper exposes the
        // whole prompts API and auto-tracks new helpers upstream adds.
        if ($this->promptManager->has($method)) {
            $this->result = $this->promptManager->$method(...$arguments);

            return $this;
        }

        throw PrompterException::triggerErrorMessage('method_does_not_exist', ['method' => $method, 'class' => static::class]);
    }

    /**
     * Magic static method to dynamically call prompt methods.
     *
     * @param string $method The name of the method.
     * @param array $arguments The arguments to pass to the method.
     *
     * @throws PrompterException If the method does not exist.
     */
    public static function __callStatic(string $method, array $arguments): self
    {
        return self::create()->__call($method, $arguments);
    }

    /**
     * Provides access to context-related methods.
     */
    public function context(): ContextBuilderService
    {
        return $this->promptManager->context();
    }

    /**
     * Get the result of the last prompt.
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Get the PromptManager instance.
     */
    public function getPrompts(): PromptService
    {
        return $this->promptManager;
    }

    /**
     * Return the form builder (special-cased: returns the builder, not $this).
     */
    public function form(): FormBuilder
    {
        return form();
    }
}
