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
use WeakMap;

use function array_key_exists;
use function count;
use function is_object;
use function is_string;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Caches native reflection objects for this instance.
 */
final class Reflector implements ReflectorInterface
{
    /** @var array<string, ReflectionClass<object>> */
    private array $classes = [];

    /** @var array<string, array<string, ReflectionClassConstant>> */
    private array $classConstants = [];

    /** @var array<string, ReflectionConstant> */
    private array $constants = [];

    /** @var array<string, ReflectionEnum<UnitEnum>> */
    private array $enums = [];

    /** @var array<string, array<string, ReflectionEnumUnitCase>> */
    private array $enumUnitCases = [];

    /** @var array<string, array<string, ReflectionEnumBackedCase>> */
    private array $enumBackedCases = [];

    /** @var array<string, ReflectionExtension> */
    private array $extensions = [];

    /** @var array<string, ReflectionZendExtension> */
    private array $zendExtensions = [];

    /** @var array<string, ReflectionFunction> */
    private array $functions = [];

    /** @var WeakMap<Closure, ReflectionFunction>|null */
    private ?WeakMap $closureFunctions = null;

    /** @var array<string, array<string, ReflectionMethod>> */
    private array $methods = [];

    /** @var array<string, array<string, ReflectionParameter>> */
    private array $parameters = [];

    /** @var WeakMap<object, array<string, ReflectionParameter>>|null */
    private ?WeakMap $objectParameters = null;

    /** @var WeakMap<object, ReflectionObject<object>>|null */
    private ?WeakMap $objects = null;

    /** @var array<string, array<string, ReflectionProperty>> */
    private array $properties = [];

    /** @var WeakMap<Generator, ReflectionGenerator>|null */
    private ?WeakMap $generators = null;

    /** @var WeakMap<Fiber, ReflectionFiber>|null */
    private ?WeakMap $fibers = null;

    /** @var array<string, ReflectionReference> */
    private array $references = [];

    public function reflectClassConstant(object|string $class, string $constant): ReflectionClassConstant
    {
        $className = $this->reflectClass($class)->getName();

        return $this->classConstants[$className][$constant]
            ??= new ReflectionClassConstant($className, $constant);
    }

    public function reflectClass(object|string $class): ReflectionClass
    {
        $className = is_object($class) ? $class::class : $class;
        $lookupKey = $this->symbolKey($className);

        if (isset($this->classes[$lookupKey])) {
            return $this->classes[$lookupKey];
        }

        $reflection = new ReflectionClass($class);
        $canonicalKey = $this->symbolKey($reflection->getName());
        $reflection = $this->classes[$canonicalKey] ??= $reflection;
        $this->classes[$lookupKey] = $reflection;

        return $reflection;
    }

    private function symbolKey(string $symbol): string
    {
        if (str_starts_with($symbol, '\\')) {
            $symbol = substr($symbol, 1);
        }

        return strtolower($symbol);
    }

    public function reflectConstant(string $name): ReflectionConstant
    {
        if (isset($this->constants[$name])) {
            return $this->constants[$name];
        }

        $reflection = new ReflectionConstant($name);
        $canonicalName = $reflection->getName();
        $reflection = $this->constants[$canonicalName] ??= $reflection;
        $this->constants[$name] = $reflection;

        return $reflection;
    }

    public function reflectEnumUnitCase(UnitEnum|string $class, string $constant): ReflectionEnumUnitCase
    {
        $enum = $this->reflectEnum($class);
        $className = $enum->getName();

        if (isset($this->enumUnitCases[$className][$constant])) {
            return $this->enumUnitCases[$className][$constant];
        }

        $reflection = $enum->getCase($constant);
        $this->enumUnitCases[$className][$constant] = $reflection;

        if ($reflection instanceof ReflectionEnumBackedCase) {
            $this->enumBackedCases[$className][$constant] = $reflection;
        }

        return $reflection;
    }

    public function reflectEnum(UnitEnum|string $class): ReflectionEnum
    {
        $className = $class instanceof UnitEnum ? $class::class : $class;
        $lookupKey = $this->symbolKey($className);

        if (isset($this->enums[$lookupKey])) {
            return $this->enums[$lookupKey];
        }

        $reflection = new ReflectionEnum($class);
        $canonicalKey = $this->symbolKey($reflection->getName());
        $reflection = $this->enums[$canonicalKey] ??= $reflection;
        $this->enums[$lookupKey] = $reflection;

        return $reflection;
    }

    public function reflectEnumBackedCase(BackedEnum|string $class, string $constant): ReflectionEnumBackedCase
    {
        $enum = $this->reflectEnum($class);
        $className = $enum->getName();

        if (isset($this->enumBackedCases[$className][$constant])) {
            return $this->enumBackedCases[$className][$constant];
        }

        $reflection = new ReflectionEnumBackedCase($className, $constant);
        $this->enumBackedCases[$className][$constant] = $reflection;
        $this->enumUnitCases[$className][$constant] = $reflection;

        return $reflection;
    }

    public function reflectExtension(string $name): ReflectionExtension
    {
        $lookupKey = $this->symbolKey($name);

        if (isset($this->extensions[$lookupKey])) {
            return $this->extensions[$lookupKey];
        }

        $reflection = new ReflectionExtension($name);
        $canonicalKey = $this->symbolKey($reflection->getName());
        $reflection = $this->extensions[$canonicalKey] ??= $reflection;
        $this->extensions[$lookupKey] = $reflection;

        return $reflection;
    }

    public function reflectZendExtension(string $name): ReflectionZendExtension
    {
        $lookupKey = $this->symbolKey($name);

        if (isset($this->zendExtensions[$lookupKey])) {
            return $this->zendExtensions[$lookupKey];
        }

        $reflection = new ReflectionZendExtension($name);
        $canonicalKey = $this->symbolKey($reflection->getName());
        $reflection = $this->zendExtensions[$canonicalKey] ??= $reflection;
        $this->zendExtensions[$lookupKey] = $reflection;

        return $reflection;
    }

    public function reflectFunction(Closure|string $function): ReflectionFunction
    {
        if ($function instanceof Closure) {
            $closureFunctions = $this->closureFunctions ??= new WeakMap();

            return $closureFunctions[$function] ??= new ReflectionFunction($function);
        }

        $lookupKey = $this->symbolKey($function);

        if (isset($this->functions[$lookupKey])) {
            return $this->functions[$lookupKey];
        }

        $reflection = new ReflectionFunction($function);
        $canonicalKey = $this->symbolKey($reflection->getName());
        $reflection = $this->functions[$canonicalKey] ??= $reflection;
        $this->functions[$lookupKey] = $reflection;

        return $reflection;
    }

    public function reflectMethod(object|string $objectOrMethod, ?string $method = null): ReflectionMethod
    {
        if (null === $method) {
            $reflection = ReflectionMethod::createFromMethodName($objectOrMethod);
            $className = $reflection->getDeclaringClass()->getName();
            $methodKey = strtolower($reflection->getName());

            return $this->methods[$className][$methodKey] ??= $reflection;
        }

        $className = $this->reflectClass($objectOrMethod)->getName();
        $methodKey = strtolower($method);

        return $this->methods[$className][$methodKey]
            ??= new ReflectionMethod($className, $method);
    }

    public function reflectParameter(callable|array|string $function, int|string $parameter): ReflectionParameter
    {
        $parameterKey = $this->parameterKey($parameter);

        if (is_object($function)) {
            $objectParameters = $this->objectParameters ??= new WeakMap();
            $parameters = $objectParameters[$function] ?? [];

            if (isset($parameters[$parameterKey])) {
                return $parameters[$parameterKey];
            }

            $reflection = new ReflectionParameter($function, $parameter);
            $reflection = $parameters[$this->parameterKey(
                $reflection->getPosition(),
            )] ??= $reflection;
            $parameters[$this->parameterKey($reflection->getName())] = $reflection;
            $parameters[$parameterKey] = $reflection;
            $objectParameters[$function] = $parameters;

            return $reflection;
        }

        $callableKey = $this->callableKey($function);

        if (null !== $callableKey && isset($this->parameters[$callableKey][$parameterKey])) {
            return $this->parameters[$callableKey][$parameterKey];
        }

        $reflection = new ReflectionParameter($function, $parameter);
        $canonicalCallableKey = $this->declaringCallableKey($reflection);
        $positionKey = $this->parameterKey($reflection->getPosition());
        $nameKey = $this->parameterKey($reflection->getName());
        $reflection = $this->parameters[$canonicalCallableKey][$positionKey] ??= $reflection;
        $this->parameters[$canonicalCallableKey][$nameKey] = $reflection;

        if (null !== $callableKey) {
            $this->parameters[$callableKey][$parameterKey] = $reflection;
            $this->parameters[$callableKey][$positionKey] = $reflection;
            $this->parameters[$callableKey][$nameKey] = $reflection;
        }

        return $reflection;
    }

    private function parameterKey(int|string $parameter): string
    {
        return is_string($parameter) ? 'name:' . $parameter : 'position:' . $parameter;
    }

    /** @param array<array-key, mixed>|string $function */
    private function callableKey(array|string $function): ?string
    {
        if (is_string($function)) {
            return 'callable:' . $this->symbolKey($function);
        }

        if (
            2 !== count($function)
            || !array_key_exists(0, $function)
            || !array_key_exists(1, $function)
        ) {
            return null;
        }

        $class = $function[0];
        $method = $function[1];

        if ((!is_object($class) && !is_string($class)) || !is_string($method)) {
            return null;
        }

        $className = is_object($class) ? $class::class : $class;

        return 'method:' . $this->symbolKey($className) . "\0" . strtolower($method);
    }

    private function declaringCallableKey(ReflectionParameter $parameter): string
    {
        $function = $parameter->getDeclaringFunction();

        if ($function instanceof ReflectionMethod) {
            return 'method:'
                . $this->symbolKey($function->getDeclaringClass()->getName())
                . "\0"
                . strtolower($function->getName());
        }

        return 'function:' . $this->symbolKey($function->getName());
    }

    public function reflectObject(object $object): ReflectionObject
    {
        $objects = $this->objects ??= new WeakMap();

        return $objects[$object] ??= new ReflectionObject($object);
    }

    public function reflectProperty(object|string $class, string $property): ReflectionProperty
    {
        $className = $this->reflectClass($class)->getName();

        return $this->properties[$className][$property]
            ??= new ReflectionProperty($className, $property);
    }

    public function reflectGenerator(Generator $generator): ReflectionGenerator
    {
        $generators = $this->generators ??= new WeakMap();

        return $generators[$generator] ??= new ReflectionGenerator($generator);
    }

    public function reflectFiber(Fiber $fiber): ReflectionFiber
    {
        $fibers = $this->fibers ??= new WeakMap();

        return $fibers[$fiber] ??= new ReflectionFiber($fiber);
    }

    public function reflectReference(array $array, int|string $key): ?ReflectionReference
    {
        $reflection = ReflectionReference::fromArrayElement($array, $key);

        if (null === $reflection) {
            return null;
        }

        return $this->references[$reflection->getId()] ??= $reflection;
    }
}
