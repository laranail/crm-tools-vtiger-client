<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Exceptions;

use RuntimeException;

/**
 * Thrown when a path is refused before anything is deleted.
 *
 * Every constructor here is a refusal. There is no "best guess" about which
 * directory somebody meant to remove, and the cost of guessing wrong is a
 * directory that is not coming back.
 */
class UnsafeAssetPath extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message, int $code, protected array $context = [])
    {
        parent::__construct($message, $code);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    public static function empty(): self
    {
        return new self('A publish root cannot be empty.', 5001);
    }

    public static function malformed(string $path): self
    {
        return new self(
            "The path [{$path}] is not usable: it is empty after normalisation, or contains a null byte.",
            5002,
            ['path' => $path],
        );
    }

    public static function outsideProject(string $path, string $basePath): self
    {
        return new self(
            "The path [{$path}] resolves outside the project [{$basePath}]. "
            . 'Publish roots are always inside the application.',
            5003,
            ['path' => $path, 'base_path' => $basePath],
        );
    }

    /**
     * A root on the non-overridable deny-list.
     *
     * The list is not configurable on purpose: config can narrow the blast
     * radius, never widen it. A typo that turns `public/vendor` into `public`
     * should not be one config edit away from deleting the application.
     */
    public static function protectedRoot(string $path, string $reason): self
    {
        return new self(
            "The path [{$path}] cannot be a publish root: {$reason}.",
            5004,
            ['path' => $path, 'reason' => $reason],
        );
    }

    public static function rootTooShallow(string $path, int $depth, int $minimum): self
    {
        return new self(
            "The path [{$path}] is {$depth} level(s) below the project root, and a publish root "
            . "must be at least {$minimum}. A shallower root would put far too much in reach.",
            5005,
            ['path' => $path, 'depth' => $depth, 'minimum' => $minimum],
        );
    }

    public static function notInsideRoot(string $path, string $root): self
    {
        return new self(
            "The path [{$path}] is not inside the publish root [{$root}], so it will not be deleted.",
            5006,
            ['path' => $path, 'root' => $root],
        );
    }

    public static function escapingSymlink(string $path, string $resolved, string $root): self
    {
        return new self(
            "The path [{$path}] resolves to [{$resolved}], which is outside the publish root [{$root}]. "
            . 'A symlink inside a root does not put its target inside the root.',
            5007,
            ['path' => $path, 'resolved' => $resolved, 'root' => $root],
        );
    }

    public static function isTheRootItself(string $path): self
    {
        return new self(
            "The path [{$path}] is the publish root itself. Contents are deletable; the root is not.",
            5008,
            ['path' => $path],
        );
    }

    public static function protectedName(string $path, string $pattern): self
    {
        return new self(
            "The path [{$path}] matches the protected pattern [{$pattern}].",
            5009,
            ['path' => $path, 'pattern' => $pattern],
        );
    }
}
