# SuperKernel Reflector

`super-kernel/reflector` encapsulates PHP's native Reflection entry points behind an instance cache.
Repeating the same request through one `SuperKernel\Reflector\Reflector` instance returns the same native reflection
object whenever PHP exposes a stable identity for that target. Separate instances have independent caches.

The component requires PHP 8.4.27 or later in the 8.4.x series, `ext-reflection`, `super-kernel/contract`, and `super-kernel/attribute`. Its implementation
is `SuperKernel\Reflector\Reflector`, which implements `ReflectorInterface` and owns all reflection logic and cache
properties as instance members. `SuperKernel\Reflector\Provider\ReflectorProvider` uses
`#[FactoryProvider(ReflectorInterface::class)]`; invoking it creates a `Reflector` and initializes the static facade
with that same instance.

## Usage

```php
use SuperKernel\Contract\ReflectorInterface;

final readonly class ServiceInspector
{
    public function __construct(private ReflectorInterface $reflector)
    {
    }

    public function action(): \ReflectionMethod
    {
        return $this->reflector->reflectMethod(Service::class, 'handle');
    }
}
```

Code that does not use the container can create a reflector directly:

```php
use SuperKernel\Reflector\Reflector;

$reflector = new Reflector();
$method = $reflector->reflectMethod(Service::class, 'handle');
```

For instance calls, construct `Reflector` directly or invoke the factory. For facade calls, initialize the factory
first; DI bootstrap already does this in each process:

```php
use SuperKernel\Reflector\Facade\ReflectorFacade;
use SuperKernel\Reflector\Provider\ReflectorProvider;

$reflector = new ReflectorProvider()();
$method = $reflector->reflectMethod(Service::class, 'handle');
$sameMethod = ReflectorFacade::reflectMethod(Service::class, 'handle');
```

The facade lives in this component under `SuperKernel\Reflector\Facade`. It declares no constructor and exposes only
`__callStatic()`. The factory uses a closure bound to the facade's class scope to set
its private static reflector; no public setter is exposed. Calling it before initialization throws `LogicException`.
Each factory invocation replaces the facade's reference with its newly produced instance. Positional and named
arguments, native results, and native reflection failures pass through to that instance unchanged.
The facade shares only that instance's cache. Initializing it with another instance does not change existing
reflector instances or their caches.

`ReflectorProvider` creates a `ReflectorInterface` service. Direct composition uses `new ReflectorProvider()()`
or `new Reflector()`; service consumers inject the interface or use the initialized facade.

Every result is the corresponding native PHP reflection object, so callers retain the complete built-in inspection
API. Native name resolution, autoloading, live runtime state, and `ReflectionException` behavior are not translated
into component-specific models or exceptions.

## Supported reflection entry points

| Interface method | Native result |
| --- | --- |
| `reflectClass()` | `ReflectionClass` |
| `reflectClassConstant()` | `ReflectionClassConstant` |
| `reflectConstant()` | `ReflectionConstant` |
| `reflectEnum()` | `ReflectionEnum` |
| `reflectEnumUnitCase()` | `ReflectionEnumUnitCase` or `ReflectionEnumBackedCase` |
| `reflectEnumBackedCase()` | `ReflectionEnumBackedCase` |
| `reflectExtension()` | `ReflectionExtension` |
| `reflectZendExtension()` | `ReflectionZendExtension` |
| `reflectFunction()` | `ReflectionFunction` |
| `reflectMethod()` | `ReflectionMethod` |
| `reflectParameter()` | `ReflectionParameter` |
| `reflectObject()` | `ReflectionObject` |
| `reflectProperty()` | `ReflectionProperty` |
| `reflectGenerator()` | `ReflectionGenerator` |
| `reflectFiber()` | `ReflectionFiber` |
| `reflectReference()` | `ReflectionReference` or `null` |

`ReflectionAttribute` and the `ReflectionType` subclasses are obtained from the cached native reflections that own
them; PHP does not expose public constructors for those result types.

## Cache boundary

Symbol reflections are cached by PHP's name semantics. Class aliases are reconciled through the canonical name
reported by native reflection; class, method, function, and extension names are case-insensitive, while property,
constant, parameter, and enum-case names retain their native case-sensitive identity.

Closure functions, callable-object parameters, object reflections, generators, and fibers use weak object keys in
maps owned by the reflector instance.
`ReflectionReference` values are cached by `ReflectionReference::getId()`. A non-reference array element returns
`null` and creates no cache entry.

All cache properties and reflection methods, including private helpers, belong to `Reflector` instances. Releasing
an instance releases its caches; a container or the facade can keep an instance alive while it is in use. There is
no cache serialization, source-change invalidation, or automatic clearing API. PHP class and function declarations
cannot be replaced during one process lifetime.

## Development

Use a PHP 8.4 Composer in this package's checkout:

```bash
composer install
composer validate --strict
composer test
composer format:check
composer format
```
