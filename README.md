<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Reflector

`super-kernel/reflector` returns PHP's native reflection objects and keeps them, so that asking one reflector again for the same class, method, or other symbol returns the same object instead of reflecting it anew.

## Installation

```bash
composer require super-kernel/reflector
```

The package needs the `reflection` extension, which PHP includes by default.

## What it provides

- A reflector for classes, methods, properties, constants, enums and their cases, functions, parameters, extensions, objects, generators, fibers, and references.
- The native `Reflection*` objects with their complete API, reused within one reflector.
- Name matching as PHP does it: class aliases, a leading backslash, and case variants of class and method names find the same object.
- A factory provider that supplies `ReflectorInterface` to a SuperKernel container.
- A static facade for code that cannot receive the reflector by injection.

It does not:

- wrap the results in its own models or translate PHP's `ReflectionException`;
- share its cache between reflector instances or processes;
- notice that code changed: it has no invalidation, clearing, or serialization of its cache.

## Quick start

Reflect a class of the project. Save it as `src/Billing/Invoice.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Billing;

final class Invoice
{
    public function __construct(public readonly string $number)
    {
    }

    public function total(): int
    {
        return 0;
    }
}
```

Ask a reflector for one method in three spellings, and a second reflector once. Save the script as `bin/reflect.php`:

```php
<?php

declare(strict_types=1);

use Src\Billing\Invoice;
use SuperKernel\Reflector\Reflector;

require dirname(__DIR__) . '/vendor/autoload.php';

$reflector = new Reflector();
$method = $reflector->reflectMethod(Invoice::class, 'total');

echo $method->getReturnType(), PHP_EOL;
var_dump($method === $reflector->reflectMethod('Src\Billing\Invoice::total'));
var_dump($method === $reflector->reflectMethod('\src\billing\INVOICE', 'TOTAL'));
var_dump($method === new Reflector()->reflectMethod(Invoice::class, 'total'));
```

Run the script:

```bash
php bin/reflect.php
```

It prints:

```text
int
bool(true)
bool(true)
bool(false)
```

## Public surface

| Name | Kind | Purpose |
| --- | --- | --- |
| `SuperKernel\Contract\ReflectorInterface` | Interface | The reflector that services receive by injection. |
| `SuperKernel\Reflector\Reflector` | Class | The reflector; `new Reflector()` creates one with an empty cache. |
| `SuperKernel\Reflector\Provider\ReflectorProvider` | Class | The factory provider of `ReflectorInterface`; each call creates a new reflector and makes it the facade's. |
| `SuperKernel\Reflector\Facade\ReflectorFacade` | Class | Static calls with the methods of `ReflectorInterface`, forwarded to the reflector of the last `ReflectorProvider` call. |

## Behavior to know

- Failures are PHP's own: a missing class raises `ReflectionException` with PHP's message, such as `Class "Src\Nope" does not exist`.
- `ReflectorFacade` raises `LogicException` with the message `ReflectorFacade requires initialization through ReflectorProvider.` until `ReflectorProvider` has been called in the process. `super-kernel/di` calls it when it creates the root container.
- Class, method, function, and extension names are matched without regard to case; property, constant, parameter, and enum case names are matched exactly.
- A reflector keeps its reflections as long as it lives; reflections of objects, closures, generators, and fibers do not keep those objects alive.

## Documentation

The developer documentation describes every entry point, the cache rules, the container, and the facade with complete examples: [SuperKernel Reflector](https://github.com/super-kernel/.github/blob/main/docs/components/reflector.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).
