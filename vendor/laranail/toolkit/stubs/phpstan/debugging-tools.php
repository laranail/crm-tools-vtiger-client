<?php

/**
 * Minimal PHPStan stubs for the optional debugging tools that
 * {@see \Simtabi\Laranail\Toolkit\Support\RuntimeConfigurator} toggles.
 *
 * Each is referenced only behind a runtime `class_exists()` / `extension_loaded()`
 * guard, so the package never hard-depends on Telescope, Debugbar or Clockwork.
 * These stubs give the analyser the symbols it needs to type-check the guarded
 * calls without installing the packages; a real install supersedes them.
 */

namespace Laravel\Telescope {
    class Telescope
    {
        public static function stopRecording(): void {}

        public static function startRecording(): void {}
    }
}

namespace Barryvdh\Debugbar\Facades {
    class Debugbar
    {
        public static function disable(): void {}

        public static function enable(): void {}
    }
}

namespace Clockwork\Support\Laravel {
    class ClockworkServiceProvider {}
}
