<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Definitions;

/**
 * Declarative route group: a route file loaded inside a
 * `Route::middleware(…)->prefix(…)->group()` wrapper. Because a literal
 * middleware name and a config key are both strings (and so indistinguishable),
 * config-resolved values use explicit `*FromConfig()` variants, resolved at
 * boot after the package's config has merged.
 *
 *     RouteGroupDefinition::make('routes/api.php')
 *         ->prefixFromConfig('acme.routes.api_prefix', 'api')
 *         ->middlewareFromConfig('acme.routes.api_middleware', ['api'])
 *         ->whenConfig('acme.features.api')
 */
final class RouteGroupDefinition
{
    /** @var array<int, string> */
    private array $middleware = [];

    private ?string $middlewareConfigKey = null;

    /** @var array<int, string> */
    private array $middlewareConfigDefault = [];

    private string $prefix = '';

    private ?string $prefixConfigKey = null;

    private string $prefixConfigDefault = '';

    private ?string $name = null;

    private ?string $domain = null;

    private ?string $whenConfigKey = null;

    private function __construct(private readonly string $file) {}

    public static function make(string $file): self
    {
        return new self($file);
    }

    /**
     * @param array<int, string>|string $middleware
     */
    public function middleware(array|string $middleware): self
    {
        $this->middleware = array_values((array) $middleware);

        return $this;
    }

    /**
     * @param array<int, string> $default
     */
    public function middlewareFromConfig(string $key, array $default = []): self
    {
        $this->middlewareConfigKey = $key;
        $this->middlewareConfigDefault = $default;

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function prefixFromConfig(string $key, string $default = ''): self
    {
        $this->prefixConfigKey = $key;
        $this->prefixConfigDefault = $default;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function domain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function whenConfig(string $key): self
    {
        $this->whenConfigKey = $key;

        return $this;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function shouldRegister(): bool
    {
        return $this->whenConfigKey === null || (bool) config($this->whenConfigKey, false);
    }

    /**
     * @return array<int, string>
     */
    public function resolveMiddleware(): array
    {
        if ($this->middlewareConfigKey !== null) {
            $value = config($this->middlewareConfigKey, $this->middlewareConfigDefault);

            return array_values(is_array($value) ? $value : (array) $value);
        }

        return $this->middleware;
    }

    public function resolvePrefix(): string
    {
        if ($this->prefixConfigKey !== null) {
            return (string) config($this->prefixConfigKey, $this->prefixConfigDefault);
        }

        return $this->prefix;
    }

    public function nameValue(): ?string
    {
        return $this->name;
    }

    public function domainValue(): ?string
    {
        return $this->domain;
    }
}
