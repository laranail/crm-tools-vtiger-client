<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Config;

use Illuminate\Support\Str;
use Simtabi\Laranail\Package\Tools\Contracts\ResolverInterface;

/**
 * Resolves patterns with {variable} placeholders for namespaces, tags,
 * paths, and other dynamic values.
 */
class PatternResolver implements ResolverInterface
{
    /**
     * Available pattern variables
     *
     * @var list<string>
     */
    protected array $availableVariables = [
        'project',
        'module',
        'module_kebab',
        'module_snake',
        'prefix',
        'vendor',
        'type',
        'component_ns',
        'name',
    ];

    public function __construct(
        protected ConfigService $config
    ) {}

    /**
     * Resolve a pattern by replacing variables with values
     *
     * @param string $pattern Pattern string with {variable} placeholders
     * @param array<string, mixed> $variables Key-value pairs of variable replacements
     * @return string Resolved pattern
     */
    public function resolve(string $pattern, array $variables = []): string
    {
        // Add automatic transformations
        if (isset($variables['module']) && ! isset($variables['module_kebab'])) {
            $variables['module_kebab'] = Str::kebab($variables['module']);
        }

        if (isset($variables['module']) && ! isset($variables['module_snake'])) {
            $variables['module_snake'] = Str::snake($variables['module']);
        }

        // Replace each variable in the pattern
        foreach ($variables as $key => $value) {
            $pattern = str_replace('{' . $key . '}', (string) $value, $pattern);
        }

        return $pattern;
    }

    /**
     * Get list of available pattern variables
     *
     * @return array<string>
     */
    public function getAvailableVariables(): array
    {
        return $this->availableVariables;
    }

    /**
     * Validate that a pattern only uses available variables
     *
     * @param string $pattern Pattern to validate
     * @return bool True if valid, false if contains unknown variables
     */
    public function validatePattern(string $pattern): bool
    {
        // Extract all variables from pattern
        preg_match_all('/\{(\w+)\}/', $pattern, $matches);
        $usedVariables = $matches[1];

        return array_all($usedVariables, fn (string $variable): bool => in_array($variable, $this->availableVariables, true));
    }

    /**
     * Extract variables used in a pattern
     *
     * @param string $pattern Pattern string
     * @return array<string> List of variable names
     */
    public function extractVariables(string $pattern): array
    {
        preg_match_all('/\{(\w+)\}/', $pattern, $matches);

        return $matches[1];
    }

    /**
     * Check if pattern contains a specific variable
     *
     * @param string $pattern Pattern string
     * @param string $variable Variable name to check
     */
    public function hasVariable(string $pattern, string $variable): bool
    {
        return str_contains($pattern, '{' . $variable . '}');
    }

    /**
     * Returns true when `$input` declares at least one `{variable}`
     * placeholder this resolver knows how to substitute.
     */
    public function canResolve(string $input): bool
    {
        $variables = $this->extractVariables($input);
        if ($variables === []) {
            return false;
        }

        return array_any($variables, fn ($variable): bool => in_array($variable, $this->availableVariables, true));
    }
}
