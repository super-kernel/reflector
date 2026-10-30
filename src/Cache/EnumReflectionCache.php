<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Cache;

use ReflectionEnum;
use ReflectionException;
use UnitEnum;

/**
 * Caches enum reflections under their requested and canonical symbol keys for one reflector instance.
 *
 * @internal
 */
final class EnumReflectionCache
{
    /** @var array<string, ReflectionEnum<UnitEnum>> */
    private array $enums = [];

    /**
     * @template TEnum of UnitEnum
     *
     * @param class-string<TEnum>|TEnum $enum
     *
     * @return ReflectionEnum<TEnum>
     *
     * @throws ReflectionException When the symbol is not an enum
     */
    public function reflect(UnitEnum|string $enum): ReflectionEnum
    {
        $className = $enum instanceof UnitEnum ? $enum::class : $enum;
        $lookupKey = SymbolKey::fromName($className);

        if (isset($this->enums[$lookupKey])) {
            return $this->enums[$lookupKey];
        }

        $reflection = new ReflectionEnum($enum);
        $canonicalKey = SymbolKey::fromName($reflection->getName());
        $reflection = $this->enums[$canonicalKey] ??= $reflection;
        $this->enums[$lookupKey] = $reflection;

        return $reflection;
    }
}
