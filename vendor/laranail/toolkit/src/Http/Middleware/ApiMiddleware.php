<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Middleware;

use Simtabi\Laranail\Toolkit\Http\Concerns\MutatesPayloadKeys;

/**
 * Base for the API request/response middleware.
 *
 * Provides the recursive key-rewriting walker (via {@see MutatesPayloadKeys})
 * and a single overridable {@see ApiMiddleware::mutateKey()} hook so subclasses
 * decide how each key is transformed. The default is an identity transform; the
 * shipped subclasses opt into snake_case ⇄ camelCase by overriding `mutateKey`
 * (or by calling the concern's `camelCaseKeys()` / `snakeCaseKeys()` directly).
 */
abstract class ApiMiddleware
{
    use MutatesPayloadKeys;

    /**
     * Recursively rewrite every key of the payload using {@see ApiMiddleware::mutateKey()}.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected function mutateKeys(array $data): array
    {
        return $this->mutatePayloadKeys($data, fn (string $key): string => $this->mutateKey($key));
    }

    /**
     * Transform a single key. The default is identity; override to change the
     * casing convention applied to the payload (e.g. `Str::snake`/`Str::camel`).
     */
    protected function mutateKey(string $key): string
    {
        return $key;
    }
}
