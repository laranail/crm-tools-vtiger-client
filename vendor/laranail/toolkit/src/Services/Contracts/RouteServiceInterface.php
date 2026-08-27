<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

/**
 * Route checking, URL analysis, and active-CSS-class navigation helpers.
 *
 * The implementation is request-scoped: it reads the current route/request via
 * injected Router/Request instances, so it is fully testable without globals.
 */
interface RouteServiceInterface
{
    /** Get the absolute base URL for the application (scheme + host, or `app.url`). */
    public function getAppUrl(): string;

    /**
     * Whether the current route/URL matches the given name or wildcard pattern.
     *
     * Supports both exact route names and URL wildcards (e.g. `users.*`).
     */
    public function isCurrentRoute(string $routeName): bool;

    /** Whether the current route's *name* matches the given name exactly. */
    public function isRoute(string $routeName): bool;

    /** Return `$class` when the current route matches `$routeName`, otherwise an empty string. */
    public function getActiveCssClass(string $routeName, string $class = 'active'): string;

    /** Alias of {@see getActiveCssClass()} kept for the legacy facade surface. */
    public function getActiveCssClassForRoute(string $routeName, string $class = 'active'): string;

    /**
     * Whether the current URL matches the given segment (prefix-aware).
     *
     * @param bool        $strict     When true, does not match wildcard sub-paths.
     * @param string|null $paramKey   Optional query parameter key to additionally require.
     * @param mixed       $paramValue Optional query parameter value to match.
     */
    public function isUrlSegment(
        string $segment,
        bool $strict = false,
        ?string $paramKey = null,
        mixed $paramValue = null
    ): bool;

    /** Whether the last URL segment equals the given segment. */
    public function isLastUrlSegment(string $segment): bool;

    /** Whether the URL segment at the given zero-based position equals the given segment. */
    public function isUrlRequestSegment(string $segment, int $position = 0): bool;

    /** Whether a request parameter equals the given value (loose compare, non-empty). */
    public function isRequestParameter(string $key, mixed $value): bool;

    /** Get a request parameter value, or null when absent. */
    public function getRequestParameterValue(string $key): mixed;

    /**
     * Return `$class` when the URL parameter/segment is active, otherwise an empty string.
     *
     * @param string|null $segment URL segment to fall back to when `$value` is empty.
     * @param string      $key     Query parameter key (default: `tab`).
     */
    public function getActiveCssClassForUrlParameter(
        mixed $value,
        ?string $segment = null,
        string $key = 'tab',
        string $class = 'active'
    ): string;

    /**
     * Whether the request matches the given link.
     *
     * @param string $type Either `name` (route-name compare) or `url` (full-URL compare).
     */
    public function isRequestOnPage(string $link, string $type = 'name'): bool;

    /**
     * Get current route information as a plain object.
     *
     * @return object{request: array{name: ?string, path: string}, method: string, action: ?string, name: ?string}
     */
    public function getCurrentRouteInfo(): object;

    /** Get a string input value from the query string, or an empty string. */
    public function getInputValueFromQueryString(string $name): string;

    /**
     * Whether the request matches the given pattern.
     *
     * @param bool $returnBool When true returns the boolean; otherwise returns the padded class or empty string.
     */
    public function isRequest(string $request, string $class = '', bool $returnBool = false): bool|string;

    /** Resolve a route name from a URL, or null when no route matches. */
    public function getRouteNameFromUrl(string $url): ?string;

    /**
     * Return `$className` when the given route (name or list of names) is the active menu item.
     *
     * @param string|list<string> $route
     */
    public function getActiveMenuClassName(string|array $route, string $className = 'active'): string;
}
