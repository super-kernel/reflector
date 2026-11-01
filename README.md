<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Reflector

`super-kernel/reflector` encapsulates PHP's native Reflection entry points behind an instance cache. Repeating the same request through one `SuperKernel\Reflector\Reflector` instance returns the same native reflection object whenever PHP exposes a stable identity for that target. Separate instances have independent caches.

## Usage

Inject `SuperKernel\Contract\ReflectorInterface`:

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

`SuperKernel\Reflector\Provider\ReflectorProvider` supplies `ReflectorInterface` to the container. Each invocation creates a new `Reflector` and makes it the instance behind the static `ReflectorFacade`. DI bootstrap already invokes it in each process; elsewhere, invoke it before using the facade:

```php
use SuperKernel\Reflector\Facade\ReflectorFacade;
use SuperKernel\Reflector\Provider\ReflectorProvider;

$reflector = new ReflectorProvider()();
$method = $reflector->reflectMethod(Service::class, 'handle');
$sameMethod = ReflectorFacade::reflectMethod(Service::class, 'handle');
```

Calling the facade before initialization throws `LogicException`; it has no public setter. Positional and named arguments, native results, and native reflection failures pass through to the current instance unchanged. The facade shares only that instance's cache. Initializing it with another instance does not change existing reflector instances or their caches.

Every result is the corresponding native PHP reflection object, so callers retain the complete built-in inspection API. Native name resolution, autoloading, live runtime state, and `ReflectionException` behavior are not translated into component-specific models or exceptions.

## Supported reflection entry points

| Interface method          | Native result                                          |
|---------------------------|--------------------------------------------------------|
| `reflectClass()`          | `ReflectionClass`                                      |
| `reflectClassConstant()`  | `ReflectionClassConstant`                              |
| `reflectConstant()`       | `ReflectionConstant`                                   |
| `reflectEnum()`           | `ReflectionEnum`                                       |
| `reflectEnumUnitCase()`   | `ReflectionEnumUnitCase` or `ReflectionEnumBackedCase` |
| `reflectEnumBackedCase()` | `ReflectionEnumBackedCase`                             |
| `reflectExtension()`      | `ReflectionExtension`                                  |
| `reflectZendExtension()`  | `ReflectionZendExtension`                              |
| `reflectFunction()`       | `ReflectionFunction`                                   |
| `reflectMethod()`         | `ReflectionMethod`                                     |
| `reflectParameter()`      | `ReflectionParameter`                                  |
| `reflectObject()`         | `ReflectionObject`                                     |
| `reflectProperty()`       | `ReflectionProperty`                                   |
| `reflectGenerator()`      | `ReflectionGenerator`                                  |
| `reflectFiber()`          | `ReflectionFiber`                                      |
| `reflectReference()`      | `ReflectionReference` or `null`                        |

`ReflectionAttribute` and the `ReflectionType` subclasses are obtained from the cached native reflections that own them; PHP does not expose public constructors for those result types.

## Cache boundary

Symbol reflections are cached by PHP's name semantics. Class aliases are reconciled through the canonical name reported by native reflection; class, method, function, and extension names are case-insensitive, while property, constant, parameter, and enum-case names retain their native case-sensitive identity.

Closure functions, callable-object parameters, object reflections, generators, and fibers are cached under weak object keys. `ReflectionReference` values are cached by `ReflectionReference::getId()`. A non-reference array element returns `null` and creates no cache entry.

Every cache belongs to its `Reflector` instance. Releasing an instance releases its caches; a container or the facade can keep an instance alive while it is in use. There is no cache serialization, source-change invalidation, or automatic clearing API. PHP class and function declarations cannot be replaced during one process lifetime.
