<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Concerns;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;

/**
 * Recursively rewrites the keys of an array payload.
 *
 * Extracted from the legacy `ApiMiddleware::mutateKeys()` so that both the
 * request/response middleware and any consumer (e.g. a controller using
 * {@see ApiResponseTrait}) can reuse the same
 * snake_case ⇄ camelCase key conversion without duplicating the walker.
 *
 * Nested arrays are walked recursively; any {@see JsonResource} encountered is
 * resolved to its array form (via the current request) before its keys are
 * rewritten, matching the legacy behaviour but against the modern
 * `JsonResource` class (the old `Illuminate\Http\Resources\Json\Resource` was
 * removed in Laravel and would fatal if referenced).
 */
trait MutatesPayloadKeys
{
    /**
     * Recursively rewrite every key of the payload using the given transformer.
     *
     * @param array<array-key, mixed>  $data
     * @param callable(string): string $transform
     *
     * @return array<array-key, mixed>
     */
    protected function mutatePayloadKeys(array $data, callable $transform): array
    {
        $payload = [];

        foreach ($data as $key => $value) {
            if ($value instanceof JsonResource) {
                $value = $value->toArray(request());
            }

            if (is_array($value)) {
                $value = $this->mutatePayloadKeys($value, $transform);
            }

            // List indices (integers) are preserved; only string keys are rewritten.
            $newKey = is_string($key) ? $transform($key) : $key;

            $payload[$newKey] = $value;
        }

        return $payload;
    }

    /**
     * Recursively convert all string keys to camelCase.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected function camelCaseKeys(array $data): array
    {
        return $this->mutatePayloadKeys($data, static fn (string $key): string => Str::camel($key));
    }

    /**
     * Recursively convert all string keys to snake_case.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected function snakeCaseKeys(array $data): array
    {
        return $this->mutatePayloadKeys($data, static fn (string $key): string => Str::snake($key));
    }
}
