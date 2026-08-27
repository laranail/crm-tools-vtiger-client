# Livewire module

Register Livewire components by alias through a single
`LivewireServiceInterface`. Bound through a deferred provider (alias
`laranail.livewire`, facade `Livewire`). The toolkit does **not** depend on
livewire/livewire — every Livewire-touching operation is a safe no-op when the
package is absent, so the module ships harmlessly whether or not Livewire is
installed.

```php
use Simtabi\Laranail\Toolkit\Modules\Livewire\Livewire;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireService;
```

## Register components

```php
Livewire::registerComponent('my-component', \App\Livewire\MyComponent::class)
        ->registerComponents([
            'user-card' => \App\Livewire\UserCard::class,
            'cart'      => \App\Livewire\Cart::class,
        ]);
```

Collected pairs are pushed into Livewire's registry on the provider's `boot()`,
or on demand:

```php
Livewire::flush(); // no-op unless Livewire is installed
```

Inspect what has been collected, derive a stable kebab-case key, or probe for
Livewire:

```php
Livewire::getRegisteredComponents();           // ['my-component' => MyComponent::class, ...]
Livewire::generateComponentKey('MyComponent'); // 'my-component'
Livewire::isLivewireAvailable();               // bool
```

## Host-class hook

Pull `HasLivewireComponents` into any class that exposes
`getLivewireComponents(): array<string, string>`; calling
`registerLivewireComponents()` forwards each alias → class pair to the service
and flushes:

```php
use Simtabi\Laranail\Toolkit\Modules\Livewire\HasLivewireComponents;

class WidgetPack
{
    use HasLivewireComponents;

    /** @return array<string, string> */
    public function getLivewireComponents(): array
    {
        return ['widget' => \App\Livewire\Widget::class];
    }
}

new WidgetPack()->registerLivewireComponents();
```

## Safe without Livewire

`isLivewireAvailable()` is gated on `class_exists(\Livewire\Livewire::class)`.
When Livewire is not installed, `flush()` collects nothing into a registry and
simply returns — registration calls still record their aliases so they take
effect the moment Livewire becomes available.

[← Docs index](../../README.md#documentation)
