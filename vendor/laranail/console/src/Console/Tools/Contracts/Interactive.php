<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Contracts;

/**
 * Marks a side-effecting component — one that reads input and/or writes/animates
 * the terminal over time (Spinner, ProgressBar, TaskProgress, Menu, Button, Live),
 * as opposed to a pure {@see Renderable} that returns a static block of lines.
 *
 * Implementations MUST degrade to a single, non-animated render when the terminal
 * is not interactive (piped / CI), emitting no cursor-control sequences.
 *
 * @api Stable contract (SemVer-covered).
 */
interface Interactive {}
