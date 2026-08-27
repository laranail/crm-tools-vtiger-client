<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            self::logAudit($model, 'created', [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            self::logAudit($model, 'updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model) {
            self::logAudit($model, 'deleted', $model->getOriginal());
        });
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    private static function logAudit(Model $model, string $event, array $oldValues = [], array $newValues = []): void
    {
        // Auditing must never break the host model's write.
        try {
            DB::table('model_audits')->insert([
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'event' => $event,
                'old_values' => json_encode(self::redactHidden($model, $oldValues)),
                'new_values' => json_encode(self::redactHidden($model, $newValues)),
                'user_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Mask the model's hidden attributes (passwords, tokens, ...) so they are
     * never persisted in the audit trail.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function redactHidden(Model $model, array $values): array
    {
        foreach ($model->getHidden() as $hidden) {
            if (array_key_exists($hidden, $values)) {
                $values[$hidden] = '[REDACTED]';
            }
        }

        return $values;
    }
}
