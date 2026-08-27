<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Facades;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Laravel\Prompts\FormBuilder;
use Simtabi\Laranail\Console\Prompter\Prompter;
use Simtabi\Laranail\Console\Prompter\Services\Components\ContextBuilderService;

/**
 * Class PrompterFacade
 *
 * This class provides a fluent interface for chaining prompt method calls.
 *
 * @api Stable public entry point for the Prompter (SemVer-covered).
 *
 * @method static Prompter text(string $label, string $placeholder = '', string $default = '', bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static Prompter textarea(string $label, string $placeholder = '', string $default = '', bool|string $required = false, ?Closure $validate = null, string $hint = '', int $rows = 5)
 * @method static Prompter password(string $label, string $placeholder = '', bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static Prompter select(string $label, array|Collection $options, int|string|null $default = null, int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true)
 * @method static Prompter multiselect(string $label, array|Collection $options, array|Collection $default = [], int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = 'Use the space bar to select options.')
 * @method static Prompter confirm(string $label, bool $default = true, string $yes = 'Yes', string $no = 'No', bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static Prompter pause(string $message = 'Press enter to continue...')
 * @method static Prompter suggest(string $label, array|Collection|Closure $options, string $placeholder = '', string $default = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static Prompter search(string $label, Closure $options, string $placeholder = '', int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true)
 * @method static Prompter multisearch(string $label, Closure $options, string $placeholder = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = 'Use the space bar to select options.')
 * @method static Prompter spin(Closure $callback, string $message = '')
 * @method static Prompter note(string $message, ?string $type = null)
 * @method static FormBuilder form()
 * @method static ContextBuilderService context()
 * @method static Prompter error(string $message)
 * @method static Prompter warning(string $message)
 * @method static Prompter alert(string $message)
 * @method static Prompter info(string $message)
 * @method static Prompter intro(string $message)
 * @method static Prompter outro(string $message)
 * @method static Prompter table(array|Collection $headers = [], array|Collection|null $rows = null)
 * @method static Prompter progress(string $label, iterable|int $steps, ?Closure $callback = null, string $hint = '')
 * @method static Prompter number(string $label, string $placeholder = '', int|string $default = '', ?int $min = null, ?int $max = null, int $step = 1, bool|string $required = false, mixed $validate = null, string $hint = '')
 * @method static Prompter autocomplete(string $label, array|Collection|Closure $options, string $placeholder = '', string $default = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = '', ?int $matches = null)
 * @method static Prompter clear()
 * @method static Prompter title(string $message)
 * @method static Prompter notify(string $title, string $message)
 * @method static Prompter task(string $label, ?Closure $callback = null, string $hint = '')
 * @method static Prompter datatable(array|Collection $columns = [], array|Collection|null $rows = null)
 * @method static Prompter grid(array|Collection $items = [], int $columns = 3)
 * @method static Prompter stream(iterable|Closure $stream)
 *
 * @see Prompter
 */
class PrompterFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Prompter::class;
    }
}
