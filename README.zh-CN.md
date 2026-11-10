<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Reflector

`super-kernel/reflector` 返回 PHP 原生的反射对象并保留它们，因此再次向同一个反射器请求同一个类、方法或其他符号时，会返回同一个对象，而不是重新反射。

## 安装

```bash
composer require super-kernel/reflector
```

本包需要 PHP 默认自带的 `reflection` 扩展。

## 提供的能力

- 一个反射器，覆盖类、方法、属性、常量、枚举及其成员、函数、参数、扩展、对象、生成器、纤程和引用。
- 带有完整 API 的原生 `Reflection*` 对象，在同一个反射器中复用。
- 与 PHP 一致的名称匹配：类别名、开头的反斜杠以及类名和方法名的大小写变体都会找到同一个对象。
- 一个为 SuperKernel 容器提供 `ReflectorInterface` 的工厂提供者。
- 一个静态门面，供无法通过注入接收反射器的代码使用。

它不会：

- 把结果包装成自己的模型，或者转换 PHP 的 `ReflectionException`；
- 在反射器实例之间或进程之间共享缓存；
- 察觉代码的变化：它的缓存没有失效、清除或序列化机制。

## 快速开始

反射项目中的一个类。将它保存为 `src/Billing/Invoice.php`：

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

用三种写法向一个反射器请求同一个方法，再向第二个反射器请求一次。将脚本保存为 `bin/reflect.php`：

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

运行脚本：

```bash
php bin/reflect.php
```

它会打印：

```text
int
bool(true)
bool(true)
bool(false)
```

## 公开接口

| 名称 | 类别 | 用途 |
| --- | --- | --- |
| `SuperKernel\Contract\ReflectorInterface` | 接口 | 服务通过注入接收的反射器。 |
| `SuperKernel\Reflector\Reflector` | 类 | 反射器；`new Reflector()` 创建一个缓存为空的反射器。 |
| `SuperKernel\Reflector\Provider\ReflectorProvider` | 类 | `ReflectorInterface` 的工厂提供者；每次调用都创建一个新的反射器，并让门面使用它。 |
| `SuperKernel\Reflector\Facade\ReflectorFacade` | 类 | 以 `ReflectorInterface` 的方法进行静态调用，转发给最近一次 `ReflectorProvider` 调用所创建的反射器。 |

## 需要了解的行为

- 失败由 PHP 自己报告：类不存在时抛出带有 PHP 消息的 `ReflectionException`，例如 `Class "Src\Nope" does not exist`。
- 在进程中调用 `ReflectorProvider` 之前，`ReflectorFacade` 抛出 `LogicException`，消息为 `ReflectorFacade requires initialization through ReflectorProvider.`。`super-kernel/di` 在创建根容器时会调用它。
- 类名、方法名、函数名和扩展名的匹配不区分大小写；属性、常量、参数和枚举成员的名称按原样精确匹配。
- 反射器在存活期间保留它的反射；对象、闭包、生成器和纤程的反射不会使这些对象保持存活。

## 文档

开发者文档用完整示例介绍每个入口、缓存规则、容器和门面：[SuperKernel Reflector](https://github.com/super-kernel/.github/blob/main/docs/components/reflector.zh-CN.md)。

## 许可证

基于 MIT 许可证发布，详见 [LICENSE](LICENSE)。
