<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Audit;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Posts package@version pairs from composer.lock to OSV.dev's batch query
 * endpoint and aggregates the returned advisories.
 *
 * Reference: https://google.github.io/osv.dev/post-v1-querybatch/
 */
final readonly class OsvAuditService
{
    private const string OSV_BATCH_URL = 'https://api.osv.dev/v1/querybatch';

    private const string OSV_VULN_URL = 'https://api.osv.dev/v1/vulns/';

    public function __construct(
        private string $projectRoot,
        private int $timeoutSeconds = 30,
    ) {
        if ($this->timeoutSeconds < 1 || $this->timeoutSeconds > 600) {
            throw new RuntimeException(
                "OsvAuditService timeout must be between 1 and 600 seconds (got {$this->timeoutSeconds})."
            );
        }
    }

    /**
     * Strip ANSI control sequences from network-supplied text so they can't
     * rewrite the operator's terminal scrollback or spoof prompts.
     */
    private static function sanitizeRemote(string $value): string
    {
        $stripped = preg_replace('/\x1B\[[0-9;?]*[A-Za-z]/', '', $value) ?? '';

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $stripped) ?? '';
    }

    /**
     * Run an audit. Returns a list of advisories per affected package.
     *
     * @param bool $includeDev Include packages-dev entries.
     * @return array{
     *     scanned: int,
     *     vulnerable_count: int,
     *     advisories: list<array{package: string, version: string, vulns: list<array{id: string, summary: string, severity: string|null, url: string|null}>}>
     * }
     */
    public function audit(bool $includeDev = true): array
    {
        $packages = $this->loadPackages($includeDev);
        if ($packages === []) {
            return ['scanned' => 0, 'vulnerable_count' => 0, 'advisories' => []];
        }

        $batch = $this->postBatchQuery($packages);
        $advisories = [];
        $vulnerable = 0;

        foreach ($batch as $i => $result) {
            $vulns = $result['vulns'] ?? [];
            if (! is_array($vulns)) {
                continue;
            }
            if ($vulns === []) {
                continue;
            }
            $vulnerable++;
            $pkg = $packages[$i];
            $advisories[] = [
                'package' => $pkg['name'],
                'version' => $pkg['version'],
                'vulns' => array_map(static function (mixed $v): array {
                    $v = is_array($v) ? $v : [];

                    $id = self::sanitizeRemote((string) ($v['id'] ?? 'UNKNOWN'));

                    return [
                        'id' => $id,
                        'summary' => self::sanitizeRemote((string) ($v['summary'] ?? '')),
                        'severity' => isset($v['severity'][0]['score'])
                            ? self::sanitizeRemote((string) $v['severity'][0]['score'])
                            : null,
                        'url' => $id !== '' && $id !== 'UNKNOWN'
                            ? self::OSV_VULN_URL . rawurlencode($id)
                            : null,
                    ];
                }, array_values($vulns)),
            ];
        }

        return [
            'scanned' => count($packages),
            'vulnerable_count' => $vulnerable,
            'advisories' => $advisories,
        ];
    }

    /**
     * @return list<array{name: string, version: string}>
     */
    private function loadPackages(bool $includeDev): array
    {
        $path = $this->projectRoot . '/composer.lock';
        if (! File::isFile($path)) {
            throw new RuntimeException(
                "composer.lock not found at: {$path}. Run `composer install` first."
            );
        }

        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException("composer.lock is not a JSON object: {$path}");
        }

        $sources = [(array) Arr::get($data, 'packages', [])];
        if ($includeDev) {
            $sources[] = (array) Arr::get($data, 'packages-dev', []);
        }

        $out = [];
        foreach ($sources as $bucket) {
            foreach ($bucket as $pkg) {
                $version = (string) Arr::get($pkg, 'version', '');
                $out[] = [
                    'name' => (string) Arr::get($pkg, 'name', ''),
                    'version' => Str::startsWith($version, 'v') ? Str::after($version, 'v') : $version,
                ];
            }
        }

        return $out;
    }

    /**
     * @param list<array{name: string, version: string}> $packages
     * @return list<array<string, mixed>>
     */
    private function postBatchQuery(array $packages): array
    {
        $payload = [
            'queries' => array_map(static fn (array $p): array => [
                'package' => ['name' => $p['name'], 'ecosystem' => 'Packagist'],
                'version' => $p['version'],
            ], $packages),
        ];

        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post(self::OSV_BATCH_URL, $payload);

        if (! $response->successful()) {
            // The body is attacker-influenceable (proxy/MITM/compromised
            // endpoint) and surfaces straight into the operator's terminal;
            // strip ANSI/control bytes so it can't rewrite the scrollback.
            $body = self::sanitizeRemote($response->body());

            throw new RuntimeException(
                "OSV.dev request failed (HTTP {$response->status()}): {$body}"
            );
        }

        $body = $response->json();
        if (! is_array($body) || ! Arr::has($body, 'results') || ! is_array($body['results'])) {
            throw new RuntimeException('OSV.dev response missing `results` array');
        }

        return array_values($body['results']);
    }
}
