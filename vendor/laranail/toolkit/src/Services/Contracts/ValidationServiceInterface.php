<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Illuminate\Support\HtmlString;

/**
 * View-layer validation helpers: error-bag message rendering, conditional CSS
 * classes, checkbox state and old-input resolution.
 *
 * All HTML-producing methods escape every interpolated value (field key,
 * message, CSS classes) — no raw user/request data is ever emitted unescaped.
 */
interface ValidationServiceInterface
{
    /**
     * Render the first error for `$key` as an escaped HTML block, or an empty
     * `HtmlString` when there is no error.
     */
    public function getErrorBagMessage(
        string $key,
        string $errorMsgClass = 'error-msg',
        string $wrapperClass = 'has-error',
        string $bag = 'errors'
    ): HtmlString;

    /** Return `$failedClass` when `$key` has an error, otherwise `$passedClass`. */
    public function getErrorBagMessageClass(
        string $key,
        string $passedClass = 'success',
        string $failedClass = 'error',
        string $bag = 'errors'
    ): string;

    /** Return `$failedClass` when `$key` has an error, otherwise `$passedClass`. */
    public function getHasErrorCssClass(
        string $key,
        string $passedClass = 'has-success',
        string $failedClass = 'has-error',
        string $bag = 'errors'
    ): string;

    /** Return `'checked'` when the old value is truthy, otherwise null. */
    public function getCheckboxStatus(mixed $oldValue, string $key): ?string;

    /**
     * Resolve an old-input value, falling back to a model attribute then a default.
     */
    public function oldInput(
        string $key,
        ?object $model = null,
        mixed $default = null,
        bool $returnBool = false
    ): mixed;

    /** Read a property off a model object, returning a default when absent. */
    public function fetchModelData(string $key, ?object $model = null, mixed $default = ''): mixed;

    /** Whether the given table exists on the default connection. */
    public function isValidDatabaseConnection(string $table = 'settings'): bool;
}
