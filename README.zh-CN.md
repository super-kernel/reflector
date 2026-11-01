<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Reflector

`super-kernel/reflector` 把 PHP 原生的反射入口封装在一个实例缓存之后。只要 PHP 为目标提供稳定的身份，通过同一个 `SuperKernel\Reflector\Reflector` 实例重复同样的请求，就会返回同一个原生反射对象。不同的实例拥有各自独立的缓存。

## 用法

注入 `SuperKernel\Contract\ReflectorInterface`：

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

不使用容器的代码可以直接创建反射器：

```php
use SuperKernel\Reflector\Reflector;

$reflector = new Reflector();
$method = $reflector->reflectMethod(Service::class, 'handle');
```

`SuperKernel\Reflector\Provider\ReflectorProvider` 向容器提供 `ReflectorInterface`。每次调用都会创建一个新的 `Reflector`，并让它成为静态 `ReflectorFacade` 背后的实例。DI 启动时已经在每个进程中调用过它；在其他场景下，使用 facade 之前先调用它：

```php
use SuperKernel\Reflector\Facade\ReflectorFacade;
use SuperKernel\Reflector\Provider\ReflectorProvider;

$reflector = new ReflectorProvider()();
$method = $reflector->reflectMethod(Service::class, 'handle');
$sameMethod = ReflectorFacade::reflectMethod(Service::class, 'handle');
```

在初始化之前调用 facade 会抛出 `LogicException`；它没有公开的 setter。位置参数和命名参数、原生结果以及原生反射失败，都会原样传给当前实例或从当前实例传出。facade 只共享该实例的缓存。用另一个实例初始化 facade，不会改变已有的反射器实例及其缓存。

每个结果都是对应的 PHP 原生反射对象，调用方保留完整的内置检查 API。原生的名称解析、自动加载、运行时状态和 `ReflectionException` 行为都不会被转换为组件专属的模型或异常。

## 支持的反射入口

| 接口方法                  | 原生结果                                               |
|---------------------------|--------------------------------------------------------|
| `reflectClass()`          | `ReflectionClass`                                      |
| `reflectClassConstant()`  | `ReflectionClassConstant`                              |
| `reflectConstant()`       | `ReflectionConstant`                                   |
| `reflectEnum()`           | `ReflectionEnum`                                       |
| `reflectEnumUnitCase()`   | `ReflectionEnumUnitCase` 或 `ReflectionEnumBackedCase` |
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
| `reflectReference()`      | `ReflectionReference` 或 `null`                        |

`ReflectionAttribute` 和各个 `ReflectionType` 子类，从持有它们的已缓存原生反射中获取；PHP 没有为这些结果类型提供公开的构造函数。

## 缓存边界

符号反射按 PHP 的名称语义缓存。类别名通过原生反射报告的规范名称统一；类、方法、函数和扩展名不区分大小写，而属性、常量、参数和枚举成员名保留其原生的大小写敏感身份。

闭包函数、可调用对象的参数、对象反射、生成器和 Fiber 以弱对象键缓存。`ReflectionReference` 的值按 `ReflectionReference::getId()` 缓存。非引用的数组元素返回 `null`，不会创建缓存条目。

所有缓存都属于各自的 `Reflector` 实例。释放实例即释放其缓存；在使用期间，容器或 facade 可以让实例保持存活。不提供缓存序列化、源码变更失效或自动清除 API。在一个进程的生命周期内，PHP 的类和函数声明无法被替换。
