<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the toolkit's genuinely-custom Blade directives.
 *
 * Directives that merely re-implement a native Laravel 11–13 directive
 * (`@checked`, `@selected`, `@disabled`, `@readonly`, `@required`, `@class`,
 * `@style`, `@session`, `@vite`, `@js`, `@error`, `@once`, `@pushOnce`,
 * `@prepend`, `@prependOnce`, `@fragment`, `@aware`, `@props`, `@dump`,
 * `@dd`, `@mix`/`@vite`) are intentionally not registered here. Only
 * directives with no native counterpart remain. The legacy `@kebab`/`@snake`/
 * `@camel`, `@count`, `@mix` and `@javascript` directives are deliberately
 * dropped (compile-time-only / broken / Mix-removed / missing helper).
 *
 * Registered eagerly from the root provider's boot().
 */
class BladeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerConditionalDirectives();
        $this->registerRouteDirectives();
        $this->registerTypeDirectives();
        $this->registerIconDirectives();
        $this->registerAssetDirectives();
        $this->registerInlineAssetDirectives();
        $this->registerStringDirectives();
        $this->registerFormDirectives();
    }

    /**
     * @istrue / @isfalse / @isnull / @isnotnull — null-safe truthiness blocks
     * with no native equivalent.
     */
    private function registerConditionalDirectives(): void
    {
        Blade::directive('istrue', static fn (string $expression): string => "<?php if (isset({$expression}) && (bool) ({$expression}) === true) : ?>");
        Blade::directive('endistrue', static fn (): string => '<?php endif; ?>');

        Blade::directive('isfalse', static fn (string $expression): string => "<?php if (isset({$expression}) && (bool) ({$expression}) === false) : ?>");
        Blade::directive('endisfalse', static fn (): string => '<?php endif; ?>');

        Blade::directive('isnull', static fn (string $expression): string => "<?php if (is_null({$expression})) : ?>");
        Blade::directive('endisnull', static fn (): string => '<?php endif; ?>');

        Blade::directive('isnotnull', static fn (string $expression): string => "<?php if (! is_null({$expression})) : ?>");
        Blade::directive('endisnotnull', static fn (): string => '<?php endif; ?>');
    }

    /**
     * @routeis / @routeisnot — fnmatch-based current-route checks, and
     *
     * @activeifroute — emit "active" when the current route name matches.
     */
    private function registerRouteDirectives(): void
    {
        Blade::directive('routeis', static fn (string $expression): string => "<?php if (fnmatch({$expression}, (string) \\Illuminate\\Support\\Facades\\Route::currentRouteName())) : ?>");
        Blade::directive('endrouteis', static fn (): string => '<?php endif; ?>');

        Blade::directive('routeisnot', static fn (string $expression): string => "<?php if (! fnmatch({$expression}, (string) \\Illuminate\\Support\\Facades\\Route::currentRouteName())) : ?>");
        Blade::directive('endrouteisnot', static fn (): string => '<?php endif; ?>');

        Blade::directive('activeifroute', static fn (string $expression): string => "<?php echo str_starts_with((string) \\Illuminate\\Support\\Facades\\Route::currentRouteName(), {$expression}) ? 'active' : ''; ?>");
    }

    /**
     * @instanceof / @typeof — type assertions with no native counterpart.
     */
    private function registerTypeDirectives(): void
    {
        Blade::directive('instanceof', static function (string $expression): string {
            [$value, $class] = self::twoArgs($expression);

            return "<?php if ({$value} instanceof {$class}) : ?>";
        });
        Blade::directive('endinstanceof', static fn (): string => '<?php endif; ?>');

        Blade::directive('typeof', static function (string $expression): string {
            [$value, $type] = self::twoArgs($expression);

            return "<?php if (gettype({$value}) === {$type}) : ?>";
        });
        Blade::directive('endtypeof', static fn (): string => '<?php endif; ?>');

        Blade::directive('repeat', static fn (string $expression): string => "<?php for (\$__repeat = 0; \$__repeat < (int) ({$expression}); \$__repeat++) : ?>");
        Blade::directive('endrepeat', static fn (): string => '<?php endfor; ?>');
    }

    /**
     * Icon shorthands (@fa, @fas, @far, @fal, @fab, @fad, @mdi, @glyph, @bi).
     */
    private function registerIconDirectives(): void
    {
        $iconFamilies = [
            'fa' => 'fa fa-',
            'fas' => 'fas fa-',
            'far' => 'far fa-',
            'fal' => 'fal fa-',
            'fab' => 'fab fa-',
            'fad' => 'fad fa-',
            'mdi' => 'mdi mdi-',
            'glyph' => 'glyphicons glyphicons-',
            'bi' => 'bi bi-',
        ];

        foreach ($iconFamilies as $name => $prefix) {
            Blade::directive($name, static function (string $expression) use ($prefix): string {
                [$icon, $classes] = self::twoArgs($expression);
                $icon = self::stripQuotes($icon);
                $classes = self::stripQuotes($classes);

                return '<i class="' . $prefix . trim($icon . ' ' . $classes) . '"></i>';
            });
        }
    }

    /**
     * @window — expose a value on the JS `window`, and @base64image — inline an
     * image file as a data URI. No native equivalents.
     */
    private function registerAssetDirectives(): void
    {
        Blade::directive('window', static function (string $expression): string {
            [$name, $value] = self::twoArgs($expression);
            $variable = self::stripQuotes($name);

            return implode("\n", [
                '<script>',
                "window.{$variable} = <?php echo is_array({$value}) ? json_encode({$value}) : {$value}; ?>;",
                '</script>',
            ]);
        });

        Blade::directive('base64image', static fn (string $expression): string => "<?php echo 'data:image/' . pathinfo({$expression}, PATHINFO_EXTENSION) . ';base64,' . base64_encode((string) file_get_contents({$expression})); ?>");
    }

    /**
     * Inline-asset directives with no native counterpart:
     *
     * - `@addstyle('/css/app.css')` — emit a stylesheet `<link>` to a static
     *   path; `@addstyle ... @endaddstyle` (no argument) wraps an inline
     *   `<style>` block.
     * - `@addscript('/js/app.js')` — emit a `<script src>` to a static path;
     *   `@addscript ... @endaddscript` (no argument) wraps an inline `<script>`
     *   block.
     * - `@inline('file.css'|'file.js'|'file.html')` — include a file from
     *   `public_path()` inline, wrapped in the appropriate tag.
     *
     * These take static, developer-authored asset paths (not request input), so
     * emitting their argument as a literal path is acceptable. The path is still
     * passed through {@see self::stripQuotes()} and never echoed via raw `{!! !!}`.
     */
    private function registerInlineAssetDirectives(): void
    {
        Blade::directive('addstyle', static function (string $expression): string {
            if (trim($expression) === '') {
                return '<style>';
            }

            return '<link rel="stylesheet" href="' . self::stripQuotes($expression) . '">';
        });
        Blade::directive('endaddstyle', static fn (): string => '</style>');

        Blade::directive('addscript', static function (string $expression): string {
            if (trim($expression) === '') {
                return '<script>';
            }

            return '<script src="' . self::stripQuotes($expression) . '"></script>';
        });
        Blade::directive('endaddscript', static fn (): string => '</script>');

        Blade::directive('inline', static function (string $expression): string {
            $include = "<?php include public_path({$expression}); ?>";

            $path = self::stripQuotes($expression);

            if (str_ends_with($path, '.css')) {
                return "<style>\n{$include}\n</style>";
            }

            if (str_ends_with($path, '.js')) {
                return "<script>\n{$include}\n</script>";
            }

            return $include;
        });
    }

    /**
     * String / value echo directives.
     *
     * - `@nl2br($text)` — convert newlines to `<br>`. The value is escaped with
     *   `e()` BEFORE `nl2br()`, so embedded HTML in the value cannot inject
     *   markup (only the directive's own `<br>` tags are raw). XSS-safe.
     * - `@dataAttributes($array)` — render an associative array as
     *   `data-key="value"` attributes; both keys and values are escaped with
     *   `e()`. XSS-safe.
     */
    private function registerStringDirectives(): void
    {
        Blade::directive('nl2br', static fn (string $expression): string => "<?php echo nl2br(e({$expression})); ?>");

        Blade::directive('dataAttributes', static fn (string $expression): string => "<?php echo collect((array) ({$expression}))->map(fn (\$value, \$key): string => 'data-' . e(\$key) . '=\"' . e(\$value) . '\"')->implode(' '); ?>");
    }

    /**
     * Form-helper directives with no native counterpart.
     *
     * - `@haserror('field') ... @endhaserror` — block shown when the named field
     *   has a validation error.
     * - `@returnifempty($value)` — bail out of the current view when the value
     *   is empty.
     * - `@selectedif($condition)` — echo the literal `selected` attribute.
     * - `@inputvalue($model, 'field')` — old() / model value, escaped with `e()`.
     * - `@optionvalue($model, 'field', $value)` — `selected` when old/model
     *   value matches; emits a literal attribute only.
     * - `@checkboxvalue($model, 'field')` — `checked` when truthy; literal
     *   attribute only.
     * - `@checkboxvaluefromarray($model, 'field', $array)` — `checked` when the
     *   model id is present in old() or the given array; literal attribute only.
     *
     * `@inputvalue` is the only value-echoing directive here and already wraps
     * its output in `e()`. The others emit fixed `selected`/`checked` literals.
     */
    private function registerFormDirectives(): void
    {
        Blade::directive('haserror', static fn (string $expression): string => "<?php if (isset(\$errors) && \$errors->has({$expression})) : ?>");
        Blade::directive('endhaserror', static fn (): string => '<?php endif; ?>');

        Blade::directive('returnifempty', static fn (string $expression): string => "<?php if (empty({$expression})) { return; } ?>");

        Blade::directive('selectedif', static fn (string $expression): string => "<?php echo ({$expression}) ? 'selected' : ''; ?>");

        Blade::directive('inputvalue', static function (string $expression): string {
            [$model, $field] = self::twoArgs($expression);
            $field = self::stripQuotes($field);

            return "<?php echo isset({$model}) ? e(old('{$field}', {$model}->{$field})) : e(old('{$field}')); ?>";
        });

        Blade::directive('optionvalue', static function (string $expression): string {
            [$model, $rest] = self::twoArgs($expression);
            [$field, $default] = self::twoArgs($rest);
            $field = self::stripQuotes($field);

            return "<?php if ((isset({$model}) && old('{$field}', {$model}->{$field}) == {$default}) || old('{$field}') == {$default}) { echo 'selected=\"selected\"'; } ?>";
        });

        Blade::directive('checkboxvalue', static function (string $expression): string {
            [$model, $field] = self::twoArgs($expression);
            $field = self::stripQuotes($field);

            return "<?php if ((isset({$model}) && old('{$field}', {$model}->{$field}) == 1) || old('{$field}') == 1) { echo 'checked=\"checked\"'; } ?>";
        });

        Blade::directive('checkboxvaluefromarray', static function (string $expression): string {
            [$model, $rest] = self::twoArgs($expression);
            [$field, $array] = self::twoArgs($rest);
            $field = self::stripQuotes($field);

            return "<?php if (collect(old('{$field}', []))->contains({$model}->id) || collect({$array})->contains(fn (\$item): bool => \$item == {$model}->id)) { echo 'checked=\"checked\"'; } ?>";
        });
    }

    /**
     * Split a two-argument directive expression into its trimmed parts.
     *
     * @return array{0: string, 1: string}
     */
    private static function twoArgs(string $expression): array
    {
        $parts = array_map(trim(...), explode(',', $expression, 2));

        return [$parts[0], $parts[1] ?? ''];
    }

    private static function stripQuotes(string $expression): string
    {
        return trim(str_replace(['\'', '"'], '', $expression));
    }
}
