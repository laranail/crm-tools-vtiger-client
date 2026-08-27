<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Doctor;

use Illuminate\Http\JsonResponse;

/**
 * Builds a monitoring-friendly JSON health response from a list of doctor
 * checks: 200 healthy / 503 degraded (any check failed). Lets a package's HTTP
 * health endpoint be a one-liner.
 */
final class HealthResponder
{
    /**
     * @param iterable<DoctorCheck|class-string<DoctorCheck>> $checks
     */
    public static function json(iterable $checks): JsonResponse
    {
        $service = new DoctorService;

        foreach ($checks as $check) {
            $service->register($check);
        }

        $report = $service->run();
        $summary = $service->summarise($report);
        $degraded = $summary['fail'] > 0;

        return new JsonResponse([
            'status' => $degraded ? 'degraded' : 'healthy',
            'summary' => $summary,
            'checks' => array_map(static fn (array $row): array => [
                'name' => $row['check']->name(),
                'group' => $row['group'] ?? null,
                'status' => $row['result']->status->value,
                'message' => $row['result']->message,
            ], $report),
        ], $degraded ? 503 : 200);
    }
}
