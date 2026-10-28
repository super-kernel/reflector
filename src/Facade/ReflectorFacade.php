<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Facade;

use BackedEnum;
use Closure;
use Fiber;
use Generator;
use LogicException;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionConstant;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use ReflectionExtension;
use ReflectionFiber;
use ReflectionFunction;
use ReflectionGenerator;
use ReflectionMethod;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionReference;
use ReflectionZendExtension;
use SuperKernel\Contract\ReflectorInterface;
use UnitEnum;

/**
 * @mixin ReflectorInterface
 *
 * @method static ReflectionClass          reflectClass(object|string $class)
 * @method static ReflectionClassConstant  reflectClassConstant(object|string $class, string $constant)
 * @method static ReflectionConstant       reflectConstant(string $name)
 * @method static ReflectionEnum           reflectEnum(UnitEnum|string $class)
 * @method static ReflectionEnumUnitCase   reflectEnumUnitCase(UnitEnum|string $class, string $constant)
 * @method static ReflectionEnumBackedCase reflectEnumBackedCase(BackedEnum|string $class, string $constant)
 * @method static ReflectionExtension      reflectExtension(string $name)
 * @method static ReflectionZendExtension  reflectZendExtension(string $name)
 * @method static ReflectionFunction       reflectFunction(Closure|string $function)
 * @method static ReflectionMethod         reflectMethod(object|string $objectOrMethod, ?string $method = null)
 * @method static ReflectionParameter      reflectParameter(callable|mixed[]|string $function, int|string $parameter)
 * @method static ReflectionObject         reflectObject(object $object)
 * @method static ReflectionProperty       reflectProperty(object|string $class, string $property)
 * @method static ReflectionGenerator      reflectGenerator(Generator $generator)
 * @method static ReflectionFiber          reflectFiber(Fiber $fiber)
 * @method static ReflectionReference|null reflectReference(mixed[] $array, int|string $key)
 */
final class ReflectorFacade
{
    /**
     * ReflectorProvider writes this property through a closure bound to this class.
     *
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    private static ReflectorInterface $reflector;

    /**
     * @param array<array-key, mixed> $arguments
     *
     * @throws LogicException When ReflectorProvider has not initialized the facade
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (isset(self::$reflector)) {
            return self::$reflector->{$name}(...$arguments);
        }

        throw new LogicException('ReflectorFacade requires initialization through ReflectorProvider.');
    }
}
