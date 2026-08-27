<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands\Concerns;

trait PublishesResources
{
    /** @var array<int|string, string> */
    protected array $publishes = [];

    public function publish(string ...$tag): self
    {
        $this->publishes = array_merge($this->publishes, $tag);

        return $this;
    }

    public function publishAssets(): self
    {
        return $this->publish('assets');
    }

    public function publishConfigFile(): self
    {
        return $this->publish('config');
    }

    public function publishInertiaComponents(): self
    {
        return $this->publish('inertia-components');
    }

    public function publishMigrations(): self
    {
        return $this->publish('migrations');
    }

    protected function processPublishes(): self
    {
        foreach ($this->publishes as $tag) {
            $name = str_replace('-', ' ', $tag);
            $this->comment("Publishing {$name}...");

            // packages register publishables under the namespaced tag
            // (vendor::pkg-{tag}); the bare {short-name}-{tag} form is the
            // legacy fallback. try both so publishing works either way —
            // publishing only the legacy form was silently a no-op for
            // every namespaced package.
            $this->callSilently('vendor:publish', [
                '--tag' => $this->package->getNamespacedPublishTag($tag),
            ]);
            $this->callSilently('vendor:publish', [
                '--tag' => "{$this->package->shortName()}-{$tag}",
            ]);
        }

        return $this;
    }
}
