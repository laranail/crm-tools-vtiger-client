<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Support;

/**
 * Operating-system + runtime-environment detection, so output and capability
 * heuristics can adapt (e.g. modern Windows terminals, WSL, CI). Pure reads of
 * `PHP_OS_FAMILY` + environment — no shared mutable state.
 */
final class Os
{
    public static function make(): self
    {
        return new self;
    }

    public function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public function isMacos(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    public function isLinux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    /**
     * Windows Subsystem for Linux (reports as Linux but runs under Windows).
     */
    public function isWsl(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        if (getenv('WSL_DISTRO_NAME') !== false || getenv('WSL_INTEROP') !== false) {
            return true;
        }

        $version = @file_get_contents('/proc/version');

        return is_string($version) && stripos($version, 'microsoft') !== false;
    }

    /**
     * Running inside a continuous-integration environment.
     */
    public function isCi(): bool
    {
        foreach (['CI', 'CONTINUOUS_INTEGRATION', 'GITHUB_ACTIONS', 'GITLAB_CI', 'CIRCLECI', 'TRAVIS', 'BUILDKITE'] as $var) {
            $value = getenv($var);

            if (! in_array($value, [false, '', '0'], true) && strtolower($value) !== 'false') {
                return true;
            }
        }

        return false;
    }

    /**
     * The detected terminal program (e.g. "vscode", "iTerm.app", "Apple_Terminal",
     * "Windows Terminal"), or null when unknown.
     */
    public function terminalProgram(): ?string
    {
        $program = getenv('TERM_PROGRAM');

        if (is_string($program) && $program !== '') {
            return $program;
        }

        if (getenv('WT_SESSION') !== false) {
            return 'Windows Terminal';
        }

        return null;
    }

    /**
     * A short identifier for the OS family: windows | macos | linux | unknown.
     */
    public function family(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'windows',
            'Darwin' => 'macos',
            'Linux' => 'linux',
            default => 'unknown',
        };
    }
}
