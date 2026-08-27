<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Http\Contracts\HttpStatusInterface;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps a route's JSON response in the standard envelope and rewrites its data
 * keys to camelCase.
 *
 * The envelope is intentionally **shape-compatible** with
 * {@see ApiResponseTrait}: top-level
 * `success` / `message` / `data` / `meta`, with pagination under
 * `meta.pagination` using the same field names. Use the trait when you control
 * the controller and want to build the envelope explicitly; use this middleware
 * when you want to envelope *existing* handlers (e.g. a legacy API group)
 * without touching each one.
 *
 * This middleware **globally transforms the payload of every response on the
 * routes it is attached to**. It is therefore opt-in: register it on a specific
 * route or group via the `laranail-toolkit.api-response` alias rather than in the global kernel.
 *
 * Hardening: the response body is decoded with {@see json_decode} guarded by
 * {@see json_last_error()}. If the body is not valid JSON (e.g. a streamed file
 * or HTML error page that slipped through), the response is returned
 * **untouched** rather than corrupted or fatal.
 */
class ApiResponseMiddleware extends ApiMiddleware
{
    /**
     * Handle the response.
     *
     * @param string ...$options Optional envelope tags: [metaTag, dataTag, pageTag].
     */
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        $response = $next($request);

        if (!$response instanceof JsonResponse) {
            // Only JSON API responses are enveloped; views/streams/redirects pass through.
            return $response;
        }

        $response = $this->hook($request, $response);

        // Hardening: if the body is not a paginator and not decodable JSON, the
        // response is malformed (e.g. tampered downstream) — leave it untouched
        // rather than fatal on, or silently corrupt, the bytes.
        if (!$this->isEnvelopable($response)) {
            return $response;
        }

        return $this->buildPayload($response, ...$options);
    }

    /**
     * Whether the response body can be safely enveloped: a paginator, an empty
     * body, or a syntactically valid JSON document.
     */
    private function isEnvelopable(JsonResponse $response): bool
    {
        if ($this->extractPaginator($response) instanceof LengthAwarePaginator) {
            return true;
        }

        $content = (string) $response->getContent();

        if ($content === '') {
            return true;
        }

        json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Rewrite each data key to camelCase for JS/TS clients.
     */
    protected function mutateKey(string $key): string
    {
        return Str::camel($key);
    }

    /**
     * Hook into the response before it is enveloped. Override to mutate it.
     */
    protected function hook(Request $request, JsonResponse $response): JsonResponse
    {
        return $response;
    }

    /**
     * Build the standard `{ success, message, data, meta }` envelope.
     *
     * @param string ...$options Optional envelope tags: [metaTag, dataTag, pageTag].
     */
    private function buildPayload(JsonResponse $response, string ...$options): JsonResponse
    {
        $metaTag = $options[0] ?? 'meta';
        $dataTag = $options[1] ?? 'data';
        $pageTag = $options[2] ?? 'pagination';

        $status = $response->getStatusCode();

        $payload = [
            'success' => $this->isSuccessful($status),
            'message' => $this->getStatusMessage($status),
            $metaTag => $this->getMetaBlock($response),
        ];

        $data = $this->resolveData($response, $payload, $metaTag, $pageTag);

        if ($data !== self::NO_DATA) {
            $payload[$dataTag] = is_array($data) ? $this->mutateKeys($data) : $data;
        }

        $response->setData($payload);

        return $response;
    }

    /**
     * Sentinel meaning "the response had no decodable body" — distinct from a
     * body that legitimately decoded to `null`.
     */
    private const string NO_DATA = "\0__laranail_no_data__\0";

    /**
     * Resolve the `data` portion of the payload, mutating `$payload[$metaTag]`
     * in place to add the pagination block when the response is paginated.
     *
     * @param array<string, mixed> $payload
     *
     * @return mixed The data value, or {@see ApiResponseMiddleware::NO_DATA}.
     */
    private function resolveData(
        JsonResponse $response,
        array &$payload,
        string $metaTag,
        string $pageTag,
    ): mixed {
        $paginator = $this->extractPaginator($response);

        if ($paginator instanceof LengthAwarePaginator) {
            if (!isset($payload[$metaTag]) || !is_array($payload[$metaTag])) {
                $payload[$metaTag] = [];
            }

            $payload[$metaTag][$pageTag] = $this->getPaginationBlock($paginator);

            return $paginator->items();
        }

        $content = (string) $response->getContent();

        if ($content === '') {
            return self::NO_DATA;
        }

        $decoded = json_decode($content, true);

        // Hardening: invalid JSON → leave the original response untouched.
        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::NO_DATA;
        }

        return $decoded;
    }

    /**
     * Pull a LengthAwarePaginator out of the response's original payload,
     * whether it is the paginator itself or wrapped in a resource collection.
     *
     * @return LengthAwarePaginator<array-key, mixed>|null
     */
    private function extractPaginator(JsonResponse $response): ?LengthAwarePaginator
    {
        $original = $response->getOriginalContent();

        if ($original instanceof LengthAwarePaginator) {
            return $original;
        }

        if ($original instanceof JsonResource && $original->resource instanceof LengthAwarePaginator) {
            return $original->resource;
        }

        return null;
    }

    /**
     * Whether the status code denotes success (i.e. not a 4xx/5xx).
     */
    private function isSuccessful(int $code): bool
    {
        return $code < 400;
    }

    /**
     * Human-readable reason phrase for the status code.
     */
    private function getStatusMessage(int $code): string
    {
        return HttpStatusInterface::CODES[$code] ?? 'Unknown';
    }

    /**
     * Build the `meta` block (code + status + message), merging any
     * `additionalMeta` the response carries.
     *
     * @return array<string, mixed>
     */
    private function getMetaBlock(JsonResponse $response): array
    {
        $code = $response->getStatusCode();

        $meta = [
            'code' => $code,
            'status' => $this->isSuccessful($code) ? 'success' : 'error',
            'message' => $this->getStatusMessage($code),
        ];

        if (isset($response->additionalMeta) && is_array($response->additionalMeta)) {
            /** @var array<string, mixed> $additional */
            $additional = $response->additionalMeta;
            $meta = array_merge($meta, $additional);
        }

        return $meta;
    }

    /**
     * Build the pagination block. Field names mirror
     * {@see ApiResponseTrait::paginatedResponse()}.
     *
     * @param LengthAwarePaginator<array-key, mixed> $paginator
     *
     * @return array<string, int>
     */
    private function getPaginationBlock(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'total_pages' => $paginator->lastPage(),
        ];
    }
}
