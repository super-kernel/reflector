<?php
declare(strict_types=1);

namespace SuperKernel\Reflector;

use BackedEnum;
use Closure;
use Fiber;
use Generator;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionConstant;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use ReflectionException;
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
use SuperKernel\Contract\ReflectionCollectorInterface;
use UnitEnum;
use function get_class;
use function is_object;

final class ReflectionCollector implements ReflectionCollectorInterface
{
	private static array $reflections = [
		ReflectionEnum::class           => [],
		ReflectionClass::class          => [],
		ReflectionMethod::class         => [],
		ReflectionConstant::class       => [],
		ReflectionFunction::class       => [],
		ReflectionProperty::class       => [],
		ReflectionExtension::class      => [],
		ReflectionParameter::class      => [],
		ReflectionEnumUnitCase::class   => [],
		ReflectionClassConstant::class  => [],
		ReflectionZendExtension::class  => [],
		ReflectionEnumBackedCase::class => [],
	];

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectClass(object|string $class): ReflectionClass
	{
		$class = is_object($class) ? get_class($class) : $class;

		return self::$reflections[ReflectionClass::class][$class] ??= new ReflectionClass($class);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectClassConstant(string|object $class, string $constant): ReflectionClassConstant
	{
		$class = is_object($class) ? get_class($class) : $class;

		return self::$reflections[ReflectionClassConstant::class][$class][$constant] ??= self::reflectClass($class)->getReflectionConstant($constant);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectConstant(string $name): ReflectionConstant
	{
		return self::$reflections[ReflectionConstant::class][$name] ??= new ReflectionConstant($name);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectEnum(UnitEnum|string $class): ReflectionEnum
	{
		$class = $class instanceof UnitEnum ? $class::class : $class;

		return self::$reflections[ReflectionEnum::class][$class] ??= new ReflectionEnum($class);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectEnumUnitCase(UnitEnum|string $class, string $constant): ReflectionEnumUnitCase
	{
		$class = $class instanceof UnitEnum ? $class::class : $class;

		return self::$reflections[ReflectionEnumUnitCase::class][$class][$constant] ??= self::reflectEnum($class)->getCase($constant);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectEnumBackedCase(BackedEnum|string $class, string $constant): ReflectionEnumBackedCase
	{
		$class = $class instanceof BackedEnum ? $class::class : $class;

		if (!isset(self::$reflections[ReflectionEnumBackedCase::class][$class][$constant])) {
			$reflectionEnumUnitCase = self::reflectEnumUnitCase($class, $constant);
			if (!($reflectionEnumUnitCase instanceof ReflectionEnumBackedCase)) {
				throw new ReflectionException("Enum case \"$class::$constant\" is not a backed case");
			}
			self::$reflections[ReflectionEnumBackedCase::class][$class][$constant] = $reflectionEnumUnitCase;
		}
		return self::$reflections[ReflectionEnumBackedCase::class][$class][$constant];
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectZendExtension(string $name): ReflectionZendExtension
	{
		return self::$reflections[ReflectionZendExtension::class][$name] ??= new ReflectionZendExtension($name);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectExtension(string $name): ReflectionExtension
	{
		return self::$reflections[ReflectionExtension::class][$name] ??= new ReflectionExtension($name);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectFunction(string|Closure $function): ReflectionFunction
	{
		if ($function instanceof Closure) {
			return new ReflectionFunction($function);
		}

		return self::$reflections[ReflectionFunction::class][$function] ??= new ReflectionFunction($function);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectMethod(object|string $class, string $method): ReflectionMethod
	{
		$class = is_object($class) ? get_class($class) : $class;

		return self::$reflections[ReflectionMethod::class][$class][$method] ??= self::reflectClass($class)->getMethod($method);
	}

	/**
	 * @inheritDoc
	 */
	public function reflectObject(object $class): ReflectionObject
	{
		return new ReflectionObject($class);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ReflectionException
	 */
	public function reflectProperty(object|string $class, string $property): ReflectionProperty
	{
		$class = is_object($class) ? get_class($class) : $class;

		return self::$reflections[ReflectionProperty::class][$class][$property] ??= new ReflectionProperty($class, $property);
	}

	/**
	 * @inheritDoc
	 */
	public function reflectGenerator(Generator $generator): ReflectionGenerator
	{
		return new ReflectionGenerator($generator);
	}

	/**
	 * @inheritDoc
	 */
	public function reflectFiber(Fiber $fiber): ReflectionFiber
	{
		return new ReflectionFiber($fiber);
	}

	/**
	 * @inheritDoc
	 */
	public function reflectReference(array $array, int|string $key): ?ReflectionReference
	{
		return ReflectionReference::fromArrayElement($array, $key);
	}
}