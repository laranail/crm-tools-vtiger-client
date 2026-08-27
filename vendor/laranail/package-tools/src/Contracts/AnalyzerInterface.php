<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

/**
 * Analyzes code, configuration, or structures.
 */
interface AnalyzerInterface
{
    /**
     * @param string $target Target to analyze (path, code, etc.)
     * @return array<string, mixed> Analysis results
     */
    public function analyze(string $target): array;

    /**
     * @param array<string, mixed> $findings Analysis findings
     * @param string $format Report format (json, html, text)
     * @return string Formatted report
     */
    public function getReport(array $findings, string $format = 'json'): string;
}
