<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;

/**
 * `php artisan laranail::package-tools.doctor` runs every registered DoctorCheck and
 * report status. Exits non-zero if any check returned FAIL.
 */
final class PackageDoctorCommand extends Command
{
    protected $signature = 'laranail::package-tools.doctor
        {--json : Emit JSON instead of TTY output}
        {--strict : Treat WARN as failure (exit non-zero)}';

    protected $description = 'Run package health checks (laranail/package-tools).';

    public function handle(DoctorService $service): int
    {
        $report = $service->run();
        $counts = $service->summarise($report);

        if ($this->option('json')) {
            $this->outputJson($report, $counts);
        } else {
            $this->outputTty($report, $counts);
        }

        $strict = (bool) $this->option('strict');
        if ($counts['fail'] > 0 || ($strict && $counts['warn'] > 0)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param list<array{check: DoctorCheck, result: DoctorResult}> $report
     * @param array{pass: int, warn: int, fail: int, skip: int} $counts
     */
    private function outputTty(array $report, array $counts): void
    {
        if ($report === []) {
            $this->warn('No doctor checks registered. Add one with $package->hasDoctorCheck(...).');

            return;
        }

        $this->line('');
        $this->line('<fg=cyan>laranail::package-tools.doctor</> — running ' . count($report) . ' check(s)…');
        $this->line('');

        foreach ($report as $row) {
            $status = $row['result']->status;
            $name = ($row['group'] ?? null) !== null
                ? "[{$row['group']}] " . $row['check']->name()
                : $row['check']->name();
            $msg = $row['result']->message;
            $color = $status->ansiColor();
            $reset = "\033[0m";

            $this->line(sprintf(
                '  %s%s%s  <fg=white;options=bold>%s</> — %s',
                $color,
                $status->symbol(),
                $reset,
                $name,
                $msg,
            ));

            foreach ($row['result']->detail as $key => $value) {
                $rendered = is_array($value) ? implode(', ', array_map(strval(...), $value)) : (string) $value;
                $this->line("       <fg=gray>{$key}: {$rendered}</>");
            }
        }

        $this->line('');
        $this->line(sprintf(
            '  Summary: <fg=green>%d pass</>, <fg=yellow>%d warn</>, <fg=red>%d fail</>, <fg=gray>%d skip</>',
            $counts['pass'],
            $counts['warn'],
            $counts['fail'],
            $counts['skip'],
        ));
        $this->line('');
    }

    /**
     * @param list<array{check: DoctorCheck, result: DoctorResult}> $report
     * @param array{pass: int, warn: int, fail: int, skip: int} $counts
     */
    private function outputJson(array $report, array $counts): void
    {
        $payload = [
            'summary' => $counts,
            'checks' => array_map(fn (array $row): array => [
                'name' => $row['check']->name(),
                'group' => $row['group'] ?? null,
                'description' => $row['check']->description(),
                'status' => $row['result']->status->value,
                'message' => $row['result']->message,
                'detail' => $row['result']->detail,
            ], $report),
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
