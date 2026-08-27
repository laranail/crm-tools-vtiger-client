<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * Reusable Eloquent model-observer base.
 *
 * Provides no-op implementations of the conventional model lifecycle hooks so a
 * concrete observer only overrides the events it cares about (no need to declare
 * empty methods for the rest). Register with `Model::observe(MyObserver::class)`.
 */
abstract class Observer
{
    public function retrieved(Model $model): void {}

    public function creating(Model $model): void {}

    public function created(Model $model): void {}

    public function updating(Model $model): void {}

    public function updated(Model $model): void {}

    public function saving(Model $model): void {}

    public function saved(Model $model): void {}

    public function deleting(Model $model): void {}

    public function deleted(Model $model): void {}

    public function restoring(Model $model): void {}

    public function restored(Model $model): void {}

    public function forceDeleted(Model $model): void {}
}
