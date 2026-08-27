<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor;

use Illuminate\Console\Command;

/**
 * Renders a doctor run (a list of checks) to a console command — table or JSON,
 * with a summary and the conventional exit code. Lets every package's
 * `…doctor` command be a thin shell.
 */
final class DoctorReporter
{
    /**
     * @param iterable<DoctorCheck|class-string<DoctorCheck>> $checks
     */
    public static function render(Command $cmd, iterable $checks, bool $json = false, bool $strict = false): int
    {
        $service = new DoctorService;

        foreach ($checks as $check) {
            $service->register($check);
        }

        $report = $service->run();
        $summary = $service->summarise($report);
        $failed = $summary['fail'] > 0 || ($strict && $summary['warn'] > 0);

        if ($json) {
            $cmd->getOutput()->writeln((string) json_encode([
                'status' => $summary['fail'] > 0 ? 'degraded' : 'healthy',
                'summary' => $summary,
                'checks' => array_map(static fn (array $row): array => [
                    'name' => $row['check']->name(),
                    'group' => $row['group'] ?? null,
                    'status' => $row['result']->status->value,
                    'message' => $row['result']->message,
                    'detail' => $row['result']->detail,
                ], $report),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failed ? Command::FAILURE : Command::SUCCESS;
        }

        $cmd->table(['', 'Check', 'Result'], array_map(static fn (array $row): array => [
            $row['result']->status->symbol(),
            ($row['group'] ?? null) !== null ? "[{$row['group']}] " . $row['check']->name() : $row['check']->name(),
            $row['result']->message,
        ], $report));

        $cmd->line(sprintf(
            '%d passed, %d warning(s), %d failure(s), %d skipped.',
            $summary['pass'],
            $summary['warn'],
            $summary['fail'],
            $summary['skip'],
        ));

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
