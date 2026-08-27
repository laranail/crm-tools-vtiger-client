<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Morph\Exceptions;

use RuntimeException;

/**
 * A morph alias (or a model class) that is not in the governed map. This is a CRITICAL failure, not a
 * user-facing 422 typo: the code is about to write an unresolvable pointer into a durable row. Kept
 * framework-light on purpose — a consuming package may catch this and re-throw its own domain exception
 * to preserve its error taxonomy.
 */
final class UnknownMorphAliasException extends RuntimeException
{
    public static function forAlias(string $alias): self
    {
        return new self(sprintf('Morph alias "%s" is not in the governed morph map.', $alias));
    }

    public static function forClass(string $class): self
    {
        return new self(sprintf('Model %s is not mapped to a morph alias.', $class));
    }
}
