<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: MIT
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 * SPDX-FileContributor: Henrique Moody <henriquemoody@gmail.com>
 */

namespace Respect\Validation;

use Respect\Validation\Message\ArrayFormatter;
use Respect\Validation\Message\Renderer;
use Respect\Validation\Message\StringFormatter;
use Stringable;

use function array_map;
use function array_reverse;
use function count;
use function ctype_digit;
use function explode;
use function is_int;
use function is_string;

final readonly class ResultQuery implements Stringable
{
    /** @param array<string|int, mixed> $templates */
    public function __construct(
        private Result $result,
        private Renderer $renderer,
        private StringFormatter $messageFormatter,
        private StringFormatter $fullMessageFormatter,
        private ArrayFormatter $messagesFormatter,
        private array $templates,
    ) {
    }

    public function findById(string $id): self|null
    {
        if ($this->result->id->value === $id) {
            return $this;
        }

        foreach ($this->result->children as $child) {
            $resultQuery = clone ($this, ['result' => $child]);
            if ($child->id->value === $id) {
                return $resultQuery;
            }

            return $resultQuery->findById($id);
        }

        return null;
    }

    public function findByName(string $name): self|null
    {
        if ($this->result->name?->value === $name) {
            return $this;
        }

        foreach ($this->result->children as $child) {
            $resultQuery = clone ($this, ['result' => $child]);
            if ($child->name?->value === $name) {
                return $resultQuery;
            }

            return $resultQuery->findByName($name);
        }

        return null;
    }

    public function findByPath(string|int $path): self|null
    {
        $paths = is_int($path) ? [$path] : $this->getSearchPathsFromScalar($path);
        $result = $this->findBySearchPaths($this->result, $paths);

        return match ($result) {
            null => null,
            $this->result => $this,
            default => clone ($this, ['result' => $result]),
        };
    }

    public function hasFailed(): bool
    {
        return $this->result->hasPassed == false;
    }

    public function getMessage(): string
    {
        if ($this->result->hasPassed) {
            return '';
        }

        return $this->messageFormatter->format($this->result, $this->renderer, $this->templates);
    }

    public function getFullMessage(): string
    {
        if ($this->result->hasPassed) {
            return '';
        }

        return $this->fullMessageFormatter->format($this->result, $this->renderer, $this->templates);
    }

    /** @return array<string|int, mixed> */
    public function getMessages(): array
    {
        if ($this->result->hasPassed) {
            return [];
        }

        return $this->messagesFormatter->format($this->result, $this->renderer, $this->templates);
    }

    /** @param array<string|int> $searchPaths */
    private function findBySearchPaths(Result $result, array $searchPaths): Result|null
    {
        if ($this->pathMatchesSearchPaths($this->getSearchPathsFromPath($result->path), $searchPaths)) {
            return $result;
        }

        foreach ($result->children as $child) {
            $found = $this->findBySearchPaths($child, $searchPaths);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param array<string|int> $paths
     * @param array<string|int> $searchPaths
     */
    private function pathMatchesSearchPaths(array $paths, array $searchPaths): bool
    {
        $pathLength = count($paths);
        $searchPathsLength = count($searchPaths);

        if ($pathLength !== $searchPathsLength) {
            return false;
        }

        foreach ($searchPaths as $i => $patternPart) {
            if ($patternPart === '*') {
                continue;
            }

            if ($paths[$i] !== $patternPart) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string|int> */
    private function getSearchPathsFromScalar(string|int $path): array
    {
        if (!is_string($path)) {
            return [$path];
        }

        return array_map(
            static fn(string $part): string|int => ctype_digit($part) ? (int) $part : $part,
            explode('.', $path),
        );
    }

    /** @return array<string|int> */
    private function getSearchPathsFromPath(Path|null $path): array
    {
        if ($path === null) {
            return [];
        }

        $parts = [];
        $current = $path;
        while ($current !== null) {
            $parts[] = $current->value;
            $current = $current->parent;
        }

        return array_reverse($parts);
    }

    public function __toString(): string
    {
        return $this->getMessage();
    }
}
