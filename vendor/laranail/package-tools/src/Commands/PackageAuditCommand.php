<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Simtabi\Laranail\Package\Tools\Services\Audit\OsvAuditService;
use Throwable;

/**
 * `php artisan laranail::package-tools.audit` queries OSV.dev for known vulnerabilities in
 * the packages locked into composer.lock. Exits non-zero if any are found.
 */
final class PackageAuditCommand extends Command
{
    protected $signature = 'laranail::package-tools.audit
        {--no-dev : Skip packages-dev (audit production deps only)}
        {--json : Emit JSON instead of TTY output}
        {--timeout=30 : HTTP timeout for OSV.dev requests, in seconds}';

    protected $description = 'Audit composer.lock against OSV.dev (laranail/package-tools).';

    public function handle(): int
    {
        $service = new OsvAuditService(base_path(), (int) $this->option('timeout'));

        try {
            $report = $service->audit(includeDev: ! $this->option('no-dev'));
        } catch (Throwable $e) {
            $this->error('Audit failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->renderTty($report);
        }

        return $report['vulnerable_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array{scanned: int, vulnerable_count: int, advisories: list<array{package: string, version: string, vulns: list<array{id: string, summary: string, severity?: string|null, url?: string|null}>}>} $report
     */
    private function renderTty(array $report): void
    {
        $this->line('');
        $this->line(sprintf(
            '<fg=cyan>laranail::package-tools.audit</> — scanned <fg=white;options=bold>%d</> packages',
            $report['scanned'],
        ));

        if ($report['vulnerable_count'] === 0) {
            $this->line('');
            $this->info('No known vulnerabilities found.');
            $this->line('');

            return;
        }

        $this->line('');
        $this->error(sprintf(
            'Found vulnerabilities in %d package(s):',
            $report['vulnerable_count'],
        ));
        $this->line('');

        foreach ($report['advisories'] as $adv) {
            $this->line(sprintf(
                '  <fg=red>✘</> <fg=white;options=bold>%s</>@%s',
                $adv['package'],
                $adv['version'],
            ));
            foreach ($adv['vulns'] as $vuln) {
                $severity = $vuln['severity'] ?? 'unknown';
                $url = $vuln['url'] ?? '';
                $this->line(sprintf(
                    '       <fg=gray>%s [%s]</> %s',
                    $vuln['id'],
                    $severity,
                    $vuln['summary'],
                ));
                if ($url !== '') {
                    $this->line("       <fg=gray>{$url}</>");
                }
            }
        }
        $this->line('');
    }
}
