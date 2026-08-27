<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Commands;

use Carbon\FactoryImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Regenerate the IDE-helper stub from the *live, registered* toolkit macros.
 *
 * Unlike the legacy `ide-helper:macros` command (which walked a static config
 * list of classes), this reflects the macros that are actually registered at
 * runtime on each macroable target, then writes a single stub file declaring
 * each macro as a `@method`/`@method static` tag on a re-opened framework class.
 * That is exactly the contract the committed `ide-helper/_ide_helper_macros.php`
 * stub guarantees, so this command can regenerate it (and the drift test stays
 * green because the output is derived from the same registered macros it asserts).
 */
class IdeHelperMacros extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    protected array $commandAliases = ['ide-helper:macros'];

    protected $signature = 'laranail::toolkit.ide-helper-macros
        {--path= : Output path for the stub (default: ide-helper/_ide_helper_macros.php under the base path)}';

    protected $description = 'Regenerate the IDE-helper stub from the toolkit\'s registered macros';

    /**
     * Macroable targets to document, keyed by stub label.
     *
     * `static` marks targets whose macros are called statically (facade-style:
     * Str, Arr); instance targets (fluent builders, Stringable, …) emit
     * non-static `@method` tags that return the target type.
     *
     * @var array<string, array{class: class-string, static: bool}>
     */
    private const TARGETS = [
        'Str' => ['class' => Str::class, 'static' => true],
        'Stringable' => ['class' => Stringable::class, 'static' => false],
        'Collection' => ['class' => Collection::class, 'static' => false],
        'Arr' => ['class' => Arr::class, 'static' => true],
        'Carbon' => ['class' => Carbon::class, 'static' => false],
        'QueryBuilder' => ['class' => QueryBuilder::class, 'static' => false],
        'EloquentBuilder' => ['class' => EloquentBuilder::class, 'static' => false],
        'Request' => ['class' => Request::class, 'static' => false],
        'ResponseFactory' => ['class' => ResponseFactory::class, 'static' => false],
    ];

    public function handle(): int
    {
        $path = $this->resolvePath();

        $this->ensureDirectoryExists(dirname($path));

        // Time the reflection-heavy stub build through the performance service so
        // the lifecycle completion log carries an honest execution-time figure.
        $performance = $this->services->performance();
        $performance->startTimer();
        $contents = $this->buildStub();
        $performance->endTimer();

        if (File::put($path, $contents) === false) {
            $this->consoleWriter()->error("Unable to write the IDE-helper stub to [{$path}].");

            return self::FAILURE;
        }

        $macroCount = $this->macrosDocumented($contents);
        $byteCount = strlen($contents);

        $this->services->metadata()->addMany([
            'path' => $path,
            'macros' => $macroCount,
            'bytes' => $byteCount,
        ]);

        // Structured completion record: how many macros were documented and the
        // stub size, alongside the timed build above.
        $this->services->logger()->logCompletion(self::SUCCESS, [
            'execution_time' => $performance->getFormattedExecutionTime(),
        ], [
            'macros' => $macroCount,
            'stub_size' => $this->services->display()->formatBytes($byteCount),
        ]);

        $this->consoleWriter()
            ->success("Regenerated IDE-helper macro stub: <fg=cyan>{$path}</>")
            ->note(sprintf(
                'Documented %d macro(s) across %d target(s) (%s).',
                $macroCount,
                count(self::TARGETS) + 1,
                $this->services->display()->formatBytes($byteCount),
            ));

        return self::SUCCESS;
    }

    /**
     * Count the `@method` tags written into the generated stub (the number of
     * macros actually documented this run).
     */
    private function macrosDocumented(string $contents): int
    {
        $count = preg_match_all('/^\s*\*\s+@method\b/m', $contents);

        return $count === false ? 0 : $count;
    }

    private function resolvePath(): string
    {
        $option = $this->option('path');

        if (is_string($option) && $option !== '') {
            return $this->isAbsolutePath($option) ? $option : base_path($option);
        }

        return base_path('ide-helper/_ide_helper_macros.php');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        File::ensureDirectoryExists($directory);
    }

    /**
     * Build the full stub file contents.
     *
     * Classes are grouped by namespace so each Illuminate namespace is re-opened
     * exactly once (multiple targets — e.g. Str/Stringable/Collection/Arr/Carbon
     * — share `Illuminate\Support`), which is required for IDEs and the drift
     * test's per-class parser to resolve every re-declared class.
     */
    private function buildStub(): string
    {
        /** @var array<string, list<string>> $classBlocksByNamespace */
        $classBlocksByNamespace = [];

        foreach (self::TARGETS as $label => $target) {
            $classBlock = $this->renderClassBlock($label, $target['class'], $target['static']);

            if ($classBlock !== null) {
                $classBlocksByNamespace[$this->namespaceOf($target['class'])][] = $classBlock;
            }
        }

        $factoryClassBlock = $this->renderFactoryMixinClassBlock();
        if ($factoryClassBlock !== null) {
            $classBlocksByNamespace[$this->namespaceOf(Factory::class)][] = $factoryClassBlock;
        }

        $blocks = [];
        foreach ($classBlocksByNamespace as $namespace => $classBlocks) {
            $blocks[] = sprintf("namespace %s {\n%s\n}", $namespace, implode("\n\n", $classBlocks));
        }

        return $this->header() . implode("\n\n", $blocks) . "\n";
    }

    /**
     * Render a single re-declared class block (docblock + empty class body) for a
     * macro target, to be wrapped in its namespace block by {@see buildStub()}.
     *
     * @param class-string $class
     */
    private function renderClassBlock(string $label, string $class, bool $static): ?string
    {
        $macros = $this->macrosFor($label, $class);

        if ($macros === []) {
            return null;
        }

        ksort($macros);

        $shortName = $this->shortNameOf($class);

        $methods = [];
        foreach ($macros as $name => $macro) {
            $methods[] = $this->renderMethodTag($name, $macro, $class, $static);
        }

        return sprintf(
            "    /**\n%s\n     */\n    class %s {}",
            implode("\n", $methods),
            $shortName,
        );
    }

    /**
     * Render the Factory mixin class block (a fixed mixin rather than a macro).
     */
    private function renderFactoryMixinClassBlock(): ?string
    {
        if (!Factory::hasMacro('withoutEvents')) {
            return null;
        }

        return sprintf(
            "    /**\n     * @method \\%s withoutEvents() Flush the model's event listeners for the factory chain.\n     */\n    class %s {}",
            ltrim(Factory::class, '\\'),
            $this->shortNameOf(Factory::class),
        );
    }

    /**
     * Resolve the live registered macros for a target.
     *
     * Most macroable targets expose their macros on a static `$macros` (or
     * `$globalMacros` for Eloquent's builder); Carbon stores them on its default
     * factory's settings. All are read here so the stub mirrors what is really
     * registered at boot.
     *
     * @param class-string $class
     *
     * @return array<string, callable>
     */
    private function macrosFor(string $label, string $class): array
    {
        if ($label === 'Carbon') {
            /** @var array<string, callable> $macros */
            $macros = FactoryImmutable::getDefaultInstance()->getSettings()['macros'] ?? [];

            return $macros;
        }

        // Every Macroable target — including Eloquent's Builder, whose
        // `macro()`/`hasGlobalMacro()` both resolve through the same static
        // `$macros` map — stores its registered macros there.
        return $this->readStaticMacros($class, 'macros');
    }

    /**
     * Read a static macro map off a class via reflection.
     *
     * @param class-string $class
     *
     * @return array<string, callable>
     */
    private function readStaticMacros(string $class, string $property): array
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->hasProperty($property)) {
            return [];
        }

        /** @var array<string, callable> $value */
        $value = (array) $reflection->getProperty($property)->getValue();

        return $value;
    }

    /**
     * Render a single `@method` tag for a macro, deriving its signature from the
     * macro callable's reflection.
     *
     * @param class-string $target
     */
    private function renderMethodTag(string $name, callable $macro, string $target, bool $static): string
    {
        $function = $this->reflectMacro($macro);

        $returnType = $static ? 'mixed' : '\\' . ltrim($target, '\\');
        if ($function instanceof ReflectionFunction || $function instanceof ReflectionMethod) {
            $resolved = $this->stringifyType($function->getReturnType());
            if ($resolved !== null) {
                $returnType = $resolved;
            }
        }

        $params = $function !== null ? $this->renderParameters($function->getParameters()) : '';

        $prefix = $static ? '@method static' : '@method';

        return sprintf('     * %s %s %s(%s)', $prefix, $returnType, $name, $params);
    }

    /**
     * Reflect a macro callable into a function/method reflection, mirroring the
     * forms Macroable accepts (Closure, [class, method], invokable object).
     */
    private function reflectMacro(callable $macro): ReflectionFunction|ReflectionMethod|null
    {
        try {
            if ($macro instanceof Closure) {
                return new ReflectionFunction($macro);
            }

            if (is_array($macro)) {
                [$class, $method] = $macro;

                return new ReflectionMethod(is_object($class) ? $class::class : $class, $method);
            }

            if (is_object($macro)) {
                return new ReflectionMethod($macro::class, '__invoke');
            }
        } catch (ReflectionException) {
            return null;
        }

        return null;
    }

    /**
     * Render a parameter list for a `@method` tag.
     *
     * @param list<ReflectionParameter> $parameters
     */
    private function renderParameters(array $parameters): string
    {
        $rendered = [];

        foreach ($parameters as $parameter) {
            $segment = '';

            $type = $this->stringifyType($parameter->getType());
            if ($type !== null) {
                $segment .= $type . ' ';
            }

            if ($parameter->isVariadic()) {
                $segment .= '...';
            }

            $segment .= '$' . $parameter->getName();

            if ($parameter->isOptional() && !$parameter->isVariadic()) {
                $segment .= ' = ' . $this->renderDefault($parameter);
            }

            $rendered[] = $segment;
        }

        return implode(', ', $rendered);
    }

    /**
     * Render a parameter's default value for the stub signature.
     */
    private function renderDefault(ReflectionParameter $parameter): string
    {
        try {
            $default = $parameter->getDefaultValue();
        } catch (ReflectionException) {
            return 'null';
        }

        return match (true) {
            $default === null => 'null',
            $default === true => 'true',
            $default === false => 'false',
            is_string($default) => var_export($default, true),
            is_array($default) => '[]',
            is_int($default), is_float($default) => (string) $default,
            default => 'null',
        };
    }

    /**
     * Render a reflection type as a stub-friendly string (FQCN-prefixed).
     */
    private function stringifyType(?\ReflectionType $type): ?string
    {
        if (!$type instanceof ReflectionNamedType) {
            // Union/intersection types are rendered loosely as `mixed` so the
            // stub stays parseable regardless of the macro's exact signature.
            return $type === null ? null : 'mixed';
        }

        $name = $type->getName();
        $prefix = !$type->isBuiltin() && $name !== 'self' && $name !== 'static' ? '\\' : '';
        $nullable = $type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '?' : '';

        return $nullable . $prefix . $name;
    }

    /**
     * @param class-string $class
     */
    private function namespaceOf(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? '' : substr($class, 0, $position);
    }

    /**
     * @param class-string $class
     */
    private function shortNameOf(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    private function header(): string
    {
        // Built line-by-line (not a heredoc) so blank lines never accrue
        // indentation/trailing whitespace in the generated file.
        $lines = [
            '<?php',
            '',
            '/**',
            ' * laranail/toolkit — IDE helper stub for runtime-registered macros.',
            ' *',
            ' * GENERATED by `php artisan laranail::toolkit.ide-helper-macros`',
            ' * (alias `ide-helper:macros`). Do NOT edit by hand — regenerate instead.',
            ' *',
            " * The toolkit registers many macros on Illuminate's macroable targets",
            ' * (Str, Stringable, Collection, Arr, the query/Eloquent builders,',
            ' * Request, Carbon) plus a Factory mixin. Those are added at boot via',
            ' * `Macroable::macro()` / `Factory::mixin()`, so a static analyser / IDE cannot',
            ' * see them and will not autocomplete or type them.',
            ' *',
            ' * This file re-opens each Illuminate namespace and re-declares the real class',
            ' * with `@method` / `@method static` PHPDoc tags for every registered macro.',
            ' * PhpStorm and VS Code (Intelephense) MERGE these tags onto the real class.',
            ' *',
            ' * IMPORTANT — this file is NEVER loaded at runtime:',
            ' *   - it is NOT listed in composer.json `autoload.files`;',
            ' *   - it lives OUTSIDE the `src/` PSR-4 root, so it is never autoloaded;',
            ' *   - IDEs index it statically (they parse, they do not execute it).',
            ' *',
            ' * Do NOT `require` this file. Do NOT add it to composer autoload.',
            ' *',
            ' * @noinspection ALL',
            ' *',
            ' * phpcs:ignoreFile',
            ' */',
            '',
            '',
        ];

        return implode("\n", $lines) . "\n";
    }
}
