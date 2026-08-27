<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Facade;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Attributes\AsFacade;
use Simtabi\Laranail\Package\Tools\Services\Discovery\AttributeDiscoverer;

/**
 * Walks classes annotated with `#[AsFacade]` and emits Laravel Facade
 * subclasses with `@method` docblocks for IDE autocomplete.
 *
 * One file per discovered contract is written under `$outputDirectory`,
 * named `<Alias>Facade.php` and namespaced under `$facadeNamespace`.
 */
final readonly class FacadeAutoGenerator
{
    public function __construct(
        private AttributeDiscoverer $discoverer = new AttributeDiscoverer,
    ) {}

    /**
     * Walk $sourceDirectory under $sourceNamespace, generate facade classes
     * into $outputDirectory under $facadeNamespace.
     *
     * @return list<array{contract: string, facade: string, alias: string, file: string}>
     */
    public function generate(
        string $sourceDirectory,
        string $sourceNamespace,
        string $outputDirectory,
        string $facadeNamespace,
    ): array {
        $this->assertValidNamespace($facadeNamespace, 'facade namespace');

        if (! File::isDirectory($outputDirectory)) {
            File::ensureDirectoryExists($outputDirectory, 0o755);
        }

        $generated = [];

        foreach ($this->discoverer->discover($sourceDirectory, $sourceNamespace, AsFacade::class) as $hit) {
            /** @var ReflectionClass<object> $rc */
            $rc = $hit['class'];

            foreach ($hit['attributes'] as $attrRef) {
                /** @var AsFacade $attr */
                $attr = $attrRef->newInstance();

                $this->assertValidIdentifier($attr->alias, 'AsFacade alias');

                $accessor = $attr->accessor ?? $rc->getName();
                $accessorExpr = $this->renderAccessorExpression($accessor);

                $facadeFqcn = rtrim($facadeNamespace, '\\') . '\\' . $attr->alias;
                $facadeFile = rtrim($outputDirectory, '/') . '/' . $attr->alias . '.php';

                $code = $this->renderFacade(
                    namespace: $facadeNamespace,
                    className: $attr->alias,
                    accessorExpr: $accessorExpr,
                    contract: $rc,
                );

                File::put($facadeFile, $code);

                $generated[] = [
                    'contract' => $rc->getName(),
                    'facade' => $facadeFqcn,
                    'alias' => $attr->alias,
                    'file' => $facadeFile,
                ];
            }
        }

        return $generated;
    }

    /**
     * Render a Facade PHP file as a string.
     *
     * @param ReflectionClass<object> $contract
     */
    private function renderFacade(
        string $namespace,
        string $className,
        string $accessorExpr,
        ReflectionClass $contract,
    ): string {
        $namespace = rtrim($namespace, '\\');
        $methods = $this->buildMethodDocs($contract);
        $methodsBlock = $methods === '' ? '' : $methods . "\n *\n";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\\Support\\Facades\\Facade;

/**
 * Auto-generated facade for {$contract->getName()}.
 *
{$methodsBlock} * @see \\{$contract->getName()}
 */
final class {$className} extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return {$accessorExpr};
    }
}

PHP;
    }

    /**
     * Render the body returned by `getFacadeAccessor()`.
     *
     * Accessors come in two shapes: PHP class/interface names (segmented by
     * `\`) become `\Foo\Bar::class`, and container binding keys (e.g.
     * `counter.service`) become `'counter.service'`.
     *
     * Anything outside `[A-Za-z0-9_\\.:-]` is rejected. The accessor is
     * interpolated into generated PHP, so this is the code-injection seam.
     */
    private function renderAccessorExpression(string $accessor): string
    {
        if ($accessor === '' || preg_match('/^[A-Za-z0-9_\\\\.:\\-]+$/', $accessor) !== 1) {
            throw new RuntimeException("Invalid AsFacade accessor: {$accessor}");
        }

        if (preg_match('/^\\\\?(?:[A-Za-z_]\w*)(?:\\\\[A-Za-z_]\w*)*$/', $accessor) === 1) {
            return '\\' . ltrim($accessor, '\\') . '::class';
        }

        return "'" . $accessor . "'";
    }

    /**
     * @param ReflectionClass<object> $contract
     */
    private function buildMethodDocs(ReflectionClass $contract): string
    {
        $methods = [];

        foreach ($contract->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isStatic()) {
                continue;
            }
            if ($m->isConstructor()) {
                continue;
            }
            if ($m->isDestructor()) {
                continue;
            }
            $params = [];
            foreach ($m->getParameters() as $p) {
                $type = $p->hasType() ? $this->renderType($p->getType()) . ' ' : '';
                $variadic = $p->isVariadic() ? '...' : '';
                $default = $p->isDefaultValueAvailable() && ! $p->isVariadic()
                    ? ' = ' . $this->renderDefault($p->getDefaultValue())
                    : '';
                $params[] = $type . $variadic . '$' . $p->getName() . $default;
            }

            $return = $m->hasReturnType() ? $this->renderType($m->getReturnType()) : 'mixed';

            $methods[] = sprintf(
                ' * @method static %s %s(%s)',
                $return,
                $m->getName(),
                implode(', ', $params),
            );
        }

        return implode("\n", $methods);
    }

    private function renderType(?ReflectionType $type): string
    {
        if (! $type instanceof ReflectionType) {
            return 'mixed';
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map($this->renderType(...), $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map($this->renderType(...), $type->getTypes()));
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            $prefix = ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') ? '?' : '';
            $name = $type->isBuiltin() ? $name : '\\' . $name;

            return $prefix . $name;
        }

        return 'mixed';
    }

    private function renderDefault(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => "'" . addcslashes($value, "'\\") . "'",
            is_array($value) => '[]',
            default => 'null',
        };
    }

    /**
     * Validate a single PHP identifier (class name, alias, etc.).
     *
     * Generator interpolates these into source code; refusing anything
     * that isn't a bare PHP identifier shuts the door on the obvious
     * code-injection vector.
     */
    private function assertValidIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_]\w*$/', $value) !== 1) {
            throw new RuntimeException("Invalid {$label}: {$value} — must be a bare PHP identifier.");
        }
    }

    /**
     * Validate a fully-qualified namespace or class name (segments joined by `\`).
     */
    private function assertValidNamespace(string $value, string $label): void
    {
        $trimmed = trim($value, '\\');
        if ($trimmed === '') {
            throw new RuntimeException("Invalid {$label}: empty namespace.");
        }
        foreach (explode('\\', $trimmed) as $segment) {
            if (preg_match('/^[A-Za-z_]\w*$/', $segment) !== 1) {
                throw new RuntimeException("Invalid {$label}: {$value} — segment '{$segment}' is not a valid PHP identifier.");
            }
        }
    }
}
