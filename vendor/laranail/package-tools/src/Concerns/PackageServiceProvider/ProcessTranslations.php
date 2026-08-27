<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

trait ProcessTranslations
{
    protected function bootPackageTranslations(): self
    {
        if (! $this->package->hasTranslations) {
            return $this;
        }

        $translationNamespace = $this->package->translationNamespace();

        $vendorTranslations = $this->package->basePath('/resources/lang');
        $appTranslations = (function_exists('lang_path'))
            ? lang_path("vendor/{$translationNamespace}")
            : resource_path("lang/vendor/{$translationNamespace}");

        $this->loadTranslationsFrom($vendorTranslations, $translationNamespace);

        // Optional short alias namespace (e.g. 'license-kit::') alongside the full one.
        $alias = $this->package->getTranslationAlias();
        if (is_string($alias) && $alias !== '') {
            $this->loadTranslationsFrom($vendorTranslations, $alias);
        }

        $this->loadJsonTranslationsFrom($vendorTranslations);
        $this->loadJsonTranslationsFrom($appTranslations);

        if ($this->app->runningInConsole()) {
            $publishTag = $this->package->getNamespacedPublishTag('translations');

            $this->publishes(
                [$vendorTranslations => $appTranslations],
                $publishTag
            );
        }

        return $this;
    }
}
