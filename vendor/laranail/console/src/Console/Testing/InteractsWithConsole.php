<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Testing;

use Laravel\Prompts\Prompt;
use PHPUnit\Framework\Attributes\After;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;

/**
 * Test helpers for code that renders this package's widgets or drives its
 * prompts. Mix into a PHPUnit/Pest test case:
 *
 *   use InteractsWithConsole;
 *
 *   $this->withConsoleCapabilities(unicode: false, width: 40);
 *   $out = Console::box('Hi')->render();          // deterministic ASCII at 40 cols
 *   self::assertStringContainsString('+', $out);
 *
 * Capability fakes are restored automatically after each test (#[After]).
 */
trait InteractsWithConsole
{
    /**
     * Force a deterministic terminal capability profile for the current test.
     * A null argument leaves that dimension to normal detection.
     */
    protected function withConsoleCapabilities(
        ?bool $colors = null,
        ?bool $unicode = null,
        ?int $width = null,
        ?bool $interactive = null,
    ): Capabilities {
        return Capabilities::fake($colors, $unicode, $width, $interactive);
    }

    /**
     * Script prompt input by delegating to laravel/prompts. Pass the keystrokes
     * to simulate (characters and Key::* constants), exactly as you would to
     * \Laravel\Prompts\Prompt::fake().
     *
     * @param array<int, string> $keys
     */
    protected function withPromptInput(array $keys = []): void
    {
        Prompt::fake($keys);
    }

    #[After]
    protected function restoreConsoleCapabilities(): void
    {
        Capabilities::clearFake();
    }
}
