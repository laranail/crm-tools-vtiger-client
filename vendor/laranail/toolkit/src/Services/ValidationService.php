<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ValidationServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;
use Throwable;

/**
 * View-layer validation helpers (error-bag rendering, conditional CSS classes,
 * checkbox state, old-input resolution).
 *
 * Security: every value interpolated into returned HTML — the field key, the
 * validation message, and every CSS class — is escaped via {@see e()} so no raw
 * user/request data is ever emitted unescaped. HTML is returned as an
 * {@see HtmlString} so Blade does not double-escape the (already-escaped) markup.
 *
 * The session store and logger are injected (no facades) so the service is
 * testable without booting the HTTP kernel.
 */
final readonly class ValidationService implements ValidationServiceInterface
{
    public function __construct(
        private Session $session,
        private LoggerInterface $logger,
    ) {}

    public function getErrorBagMessage(
        string $key,
        string $errorMsgClass = 'error-msg',
        string $wrapperClass = 'has-error',
        string $bag = 'errors'
    ): HtmlString {
        $errors = $this->errorBag($bag);

        if (!$errors instanceof MessageBag || !$errors->has($key)) {
            return new HtmlString('');
        }

        // Escape EVERY interpolated value: the message comes from validation
        // (may echo user input), and the CSS classes are caller-supplied.
        $message = e($errors->first($key));
        $wrapper = e($wrapperClass);
        $errorClass = e($errorMsgClass);

        return new HtmlString(
            "<div class=\"{$wrapper}\"><p class=\"help-block {$errorClass}\">{$message}</p></div>"
        );
    }

    public function getErrorBagMessageClass(
        string $key,
        string $passedClass = 'success',
        string $failedClass = 'error',
        string $bag = 'errors'
    ): string {
        return $this->classForKey($key, $bag, $passedClass, $failedClass);
    }

    public function getHasErrorCssClass(
        string $key,
        string $passedClass = 'has-success',
        string $failedClass = 'has-error',
        string $bag = 'errors'
    ): string {
        return $this->classForKey($key, $bag, $passedClass, $failedClass);
    }

    public function getCheckboxStatus(mixed $oldValue, string $key): ?string
    {
        // Truthy old value → render the `checked` attribute. Matches the legacy
        // `!empty()` semantics (treats '', '0', 0, null, false, [] as unchecked)
        // without the disallowed empty() construct.
        $unchecked = in_array($oldValue, ['', '0', 0, 0.0, null, false, []], true);

        return $unchecked ? null : 'checked';
    }

    public function oldInput(string $key, ?object $model = null, mixed $default = null, bool $returnBool = false): mixed
    {
        if ($this->session->has("_old_input.{$key}")) {
            $value = $this->session->get("_old_input.{$key}");
        } else {
            // data_get reads object properties / array keys safely, returning the
            // default when the attribute is absent (no dynamic property access).
            $value = $model !== null ? data_get($model, $key, $default) : $default;
        }

        if ($returnBool) {
            return (bool) Cast::toInt($value);
        }

        return $value;
    }

    public function fetchModelData(string $key, ?object $model = null, mixed $default = ''): mixed
    {
        return $model !== null ? data_get($model, $key, $default) : $default;
    }

    public function isValidDatabaseConnection(string $table = 'settings'): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $exception) {
            $this->logger->error('Database connection validation failed', [
                'table' => $table,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve and validate the named error bag from the session.
     */
    private function errorBag(string $bag): ?MessageBag
    {
        if (!$this->session->has($bag)) {
            return null;
        }

        $errors = $this->session->get($bag);

        return $errors instanceof MessageBag ? $errors : null;
    }

    /**
     * Pick the failed/passed CSS class for a key (no HTML — classes returned raw
     * for use in `class="..."` attributes the caller controls).
     */
    private function classForKey(string $key, string $bag, string $passedClass, string $failedClass): string
    {
        $errors = $this->errorBag($bag);

        if (!$errors instanceof MessageBag) {
            return $passedClass;
        }

        return $errors->has($key) ? $failedClass : $passedClass;
    }
}
