<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Cache;

use ReflectionClass;
use ReflectionException;

use function is_object;

/**
 * Caches class reflections under their requested and canonical symbol keys for one reflector instance.
 *
 * @internal
 */
final class ClassReflectionCache
{
    /** @var array<string, ReflectionClass<object>> */
    private array $classes = [];

    /**
     * @template TObject of object
     *
     * @param class-string<TObject>|TObject $class
     *
     * @return ReflectionClass<TObject>
     *
     * @throws ReflectionException When the class-like symbol does not exist or cannot be reflected
     */
    public function reflect(object|string $class): ReflectionClass
    {
        $className = is_object($class) ? $class::class : $class;
        $lookupKey = SymbolKey::fromName($className);

        if (isset($this->classes[$lookupKey])) {
            return $this->classes[$lookupKey];
        }

        $reflection = new ReflectionClass($class);
        $canonicalKey = SymbolKey::fromName($reflection->getName());
        $reflection = $this->classes[$canonicalKey] ??= $reflection;
        $this->classes[$lookupKey] = $reflection;

        return $reflection;
    }
}
