<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

/**
 * Thrown when an Eloquent model operation fails.
 */
class ModelException extends LaranailException
{
    /**
     * Missing primary key on a model.
     *
     * @param class-string|string $modelClass The model class name.
     */
    public static function missingPrimaryKey(string $modelClass): self
    {
        return new self(
            message: "No primary key defined on model: {$modelClass}",
            code: 3001,
            context: ['model' => $modelClass],
            userMessage: 'Model configuration error',
        );
    }

    /**
     * Model record not found for the given identifier.
     *
     * @param class-string|string $modelClass The model class name.
     * @param mixed               $identifier The identifier that was not found.
     */
    public static function notFound(string $modelClass, mixed $identifier): self
    {
        return new self(
            message: sprintf('Model not found: %s with identifier %s', $modelClass, self::stringifyIdentifier($identifier)),
            code: 3002,
            context: [
                'model' => $modelClass,
                'identifier' => $identifier,
            ],
            userMessage: 'Record not found',
            status: 404,
        );
    }

    /**
     * Invalid model state.
     *
     * @param class-string|string $modelClass The model class name.
     * @param string              $reason     Why the state is invalid.
     */
    public static function invalidState(string $modelClass, string $reason): self
    {
        return new self(
            message: "Invalid model state for {$modelClass}: {$reason}",
            code: 3003,
            context: [
                'model' => $modelClass,
                'reason' => $reason,
            ],
        );
    }

    /**
     * Render any identifier safely for the message string.
     */
    private static function stringifyIdentifier(mixed $identifier): string
    {
        if (is_scalar($identifier)) {
            return (string) $identifier;
        }

        if ($identifier === null) {
            return 'null';
        }

        return get_debug_type($identifier);
    }
}
