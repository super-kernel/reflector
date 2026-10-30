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
use SuperKernel\Reflector\Cache\ClassReflectionCache;
use SuperKernel\Reflector\Cache\EnumReflectionCache;
use SuperKernel\Reflector\Cache\SymbolKey;
use UnitEnum;
use WeakMap;

use function array_key_exists;
use function count;
use function is_object;
use function is_string;
use function strtolower;

/**
 * Caches native reflection objects for this instance.
 */
final class Reflector implements ReflectorInterface
{
    private ?ClassReflectionCache $classCache = null;

    /** @var array<string, array<string, ReflectionClassConstant>> */
    private array $classConstants = [];

    /** @var array<string, ReflectionConstant> */
    private array $constants = [];

    private ?EnumReflectionCache $enumCache = null;

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
        $className = ($this->classCache ??= new ClassReflectionCache())->reflect($class)->getName();

        return $this->classConstants[$className][$constant]
            ??= new ReflectionClassConstant($className, $constant);
    }

    public function reflectClass(object|string $class): ReflectionClass
    {
        return ($this->classCache ??= new ClassReflectionCache())->reflect($class);
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
        $enum = ($this->enumCache ??= new EnumReflectionCache())->reflect($class);
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
        return ($this->enumCache ??= new EnumReflectionCache())->reflect($class);
    }

    public function reflectEnumBackedCase(BackedEnum|string $class, string $constant): ReflectionEnumBackedCase
    {
        $enum = ($this->enumCache ??= new EnumReflectionCache())->reflect($class);
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
        $lookupKey = SymbolKey::fromName($name);

        if (isset($this->extensions[$lookupKey])) {
            return $this->extensions[$lookupKey];
        }

        $reflection = new ReflectionExtension($name);
        $canonicalKey = SymbolKey::fromName($reflection->getName());
        $reflection = $this->extensions[$canonicalKey] ??= $reflection;
        $this->extensions[$lookupKey] = $reflection;

        return $reflection;
    }

    public function reflectZendExtension(string $name): ReflectionZendExtension
    {
        $lookupKey = SymbolKey::fromName($name);

        if (isset($this->zendExtensions[$lookupKey])) {
            return $this->zendExtensions[$lookupKey];
        }

        $reflection = new ReflectionZendExtension($name);
        $canonicalKey = SymbolKey::fromName($reflection->getName());
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

        $lookupKey = SymbolKey::fromName($function);

        if (isset($this->functions[$lookupKey])) {
            return $this->functions[$lookupKey];
        }

        $reflection = new ReflectionFunction($function);
        $canonicalKey = SymbolKey::fromName($reflection->getName());
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

        $className = ($this->classCache ??= new ClassReflectionCache())->reflect($objectOrMethod)->getName();
        $methodKey = strtolower($method);

        return $this->methods[$className][$methodKey]
            ??= new ReflectionMethod($className, $method);
    }

    public function reflectParameter(callable|array|string $function, int|string $parameter): ReflectionParameter
    {
        $parameterKey = is_string($parameter) ? 'name:' . $parameter : 'position:' . $parameter;

        if (is_object($function)) {
            $objectParameters = $this->objectParameters ??= new WeakMap();
            $parameters = $objectParameters[$function] ?? [];

            if (isset($parameters[$parameterKey])) {
                return $parameters[$parameterKey];
            }

            $reflection = new ReflectionParameter($function, $parameter);
            $reflection = $parameters['position:' . $reflection->getPosition()] ??= $reflection;
            $parameters['name:' . $reflection->getName()] = $reflection;
            $parameters[$parameterKey] = $reflection;
            $objectParameters[$function] = $parameters;

            return $reflection;
        }

        $callableKey = null;

        if (is_string($function)) {
            $callableKey = 'callable:' . SymbolKey::fromName($function);
        } elseif (
            2 === count($function)
            && array_key_exists(0, $function)
            && array_key_exists(1, $function)
        ) {
            $class = $function[0];
            $method = $function[1];

            if ((is_object($class) || is_string($class)) && is_string($method)) {
                $className = is_object($class) ? $class::class : $class;
                $callableKey = 'method:' . SymbolKey::fromName($className) . "\0" . strtolower($method);
            }
        }

        if (null !== $callableKey && isset($this->parameters[$callableKey][$parameterKey])) {
            return $this->parameters[$callableKey][$parameterKey];
        }

        $reflection = new ReflectionParameter($function, $parameter);
        $declaringFunction = $reflection->getDeclaringFunction();

        if ($declaringFunction instanceof ReflectionMethod) {
            $canonicalCallableKey = 'method:'
                . SymbolKey::fromName($declaringFunction->getDeclaringClass()->getName())
                . "\0"
                . strtolower($declaringFunction->getName());
        } else {
            $canonicalCallableKey = 'function:' . SymbolKey::fromName($declaringFunction->getName());
        }

        $positionKey = 'position:' . $reflection->getPosition();
        $nameKey = 'name:' . $reflection->getName();
        $reflection = $this->parameters[$canonicalCallableKey][$positionKey] ??= $reflection;
        $this->parameters[$canonicalCallableKey][$nameKey] = $reflection;

        if (null !== $callableKey) {
            $this->parameters[$callableKey][$parameterKey] = $reflection;
            $this->parameters[$callableKey][$positionKey] = $reflection;
            $this->parameters[$callableKey][$nameKey] = $reflection;
        }

        return $reflection;
    }

    public function reflectObject(object $object): ReflectionObject
    {
        $objects = $this->objects ??= new WeakMap();

        return $objects[$object] ??= new ReflectionObject($object);
    }

    public function reflectProperty(object|string $class, string $property): ReflectionProperty
    {
        $className = ($this->classCache ??= new ClassReflectionCache())->reflect($class)->getName();

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
