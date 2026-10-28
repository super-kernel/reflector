<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Tests\Unit;

use Fiber;
use Generator;
use PHPUnit\Framework\TestCase;
use ReflectionEnumBackedCase;
use ReflectionException;
use ReflectionReference;
use SuperKernel\Reflector\Reflector;
use SuperKernel\Reflector\Tests\Fixture\BackedStatus;
use SuperKernel\Reflector\Tests\Fixture\ReflectionFixture;
use SuperKernel\Reflector\Tests\Fixture\UnitStatus;
use Throwable;

use function class_alias;
use function strtoupper;

final class ReflectorCacheTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCachesClassesAndClassMembersAcrossEquivalentInputs(): void
    {
        $reflector = new Reflector();

        $alias = __NAMESPACE__ . '\\ReflectionFixtureAlias';
        self::assertTrue(class_alias(ReflectionFixture::class, $alias));

        $fixture = new ReflectionFixture();
        $class = $reflector->reflectClass($alias);

        self::assertSame(ReflectionFixture::class, $class->getName());
        self::assertSame($class, $reflector->reflectClass($fixture));
        self::assertSame($class, $reflector->reflectClass('\\' . strtoupper(ReflectionFixture::class)));

        $constant = $reflector->reflectClassConstant($alias, 'LABEL');
        self::assertSame($constant, $reflector->reflectClassConstant(ReflectionFixture::class, 'LABEL'));
        self::assertSame('fixture', $constant->getValue());

        $method = $reflector->reflectMethod($alias, 'method');
        self::assertSame($method, $reflector->reflectMethod($fixture, 'METHOD'));
        self::assertSame($method, $reflector->reflectMethod(ReflectionFixture::class . '::method'));

        $property = $reflector->reflectProperty($alias, 'property');
        self::assertSame($property, $reflector->reflectProperty($fixture, 'property'));

        $parameter = $reflector->reflectParameter([
            $alias,
            'method',
        ], 0);
        self::assertSame($parameter, $reflector->reflectParameter([
            ReflectionFixture::class,
            'METHOD',
        ], 'parameter'));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCachesUnitAndBackedEnumReflectionsAndCases(): void
    {
        $reflector = new Reflector();

        $unitEnum = $reflector->reflectEnum(UnitStatus::Ready);

        self::assertSame($unitEnum, $reflector->reflectEnum(UnitStatus::class));
        self::assertSame(
            $reflector->reflectEnumUnitCase(UnitStatus::class, 'Ready'),
            $reflector->reflectEnumUnitCase(UnitStatus::Ready, 'Ready'),
        );

        $backedAsUnit = $reflector->reflectEnumUnitCase(BackedStatus::class, 'Ready');
        $backed = $reflector->reflectEnumBackedCase(BackedStatus::Ready, 'Ready');

        self::assertInstanceOf(ReflectionEnumBackedCase::class, $backedAsUnit);
        self::assertSame($backedAsUnit, $backed);
        self::assertSame('ready', $backed->getBackingValue());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCachesGlobalFunctionsConstantsAndExtensions(): void
    {
        $reflector = new Reflector();


        self::assertSame(
            $reflector->reflectFunction('strlen'),
            $reflector->reflectFunction('\\STRLEN'),
        );
        self::assertSame(
            $reflector->reflectConstant('PHP_VERSION'),
            $reflector->reflectConstant('PHP_VERSION'),
        );
        self::assertSame(
            $reflector->reflectExtension('Reflection'),
            $reflector->reflectExtension('REFLECTION'),
        );
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCachesClosureAndRuntimeObjectReflections(): void
    {
        $reflector = new Reflector();

        $closure = static fn(string $value): string => $value;

        self::assertSame(
            $reflector->reflectFunction($closure),
            $reflector->reflectFunction($closure),
        );
        self::assertSame(
            $reflector->reflectParameter($closure, 0),
            $reflector->reflectParameter($closure, 'value'),
        );

        $object = new ReflectionFixture();
        self::assertSame(
            $reflector->reflectParameter($object, 0),
            $reflector->reflectParameter($object, 'parameter'),
        );
        self::assertSame(
            $reflector->reflectObject($object),
            $reflector->reflectObject($object),
        );

        $generator = (static function (): Generator {
            yield 'value';
        })();
        self::assertSame(
            $reflector->reflectGenerator($generator),
            $reflector->reflectGenerator($generator),
        );

        $fiber = new Fiber(static function (): void {
        });
        self::assertSame(
            $reflector->reflectFiber($fiber),
            $reflector->reflectFiber($fiber),
        );
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCachesReferencesByTheirNativeIdentity(): void
    {
        $reflector = new Reflector();

        $value = 'value';
        $referenced = [
            'value' => &$value,
        ];
        $first = $reflector->reflectReference($referenced, 'value');
        $second = $reflector->reflectReference($referenced, 'value');

        self::assertInstanceOf(ReflectionReference::class, $first);
        self::assertSame($first, $second);
        self::assertSame($first->getId(), $second->getId());
        self::assertNull($reflector->reflectReference([
            'value' => $value,
        ], 'value'));
    }

    public function testPropagatesNativeFailureForAMissingReferenceKey(): void
    {
        $reflector = new Reflector();

        $this->expectException(ReflectionException::class);

        $reflector->reflectReference([], 'missing');
    }

    public function testPropagatesNativeFailureForAMissingClass(): void
    {
        $reflector = new Reflector();

        $this->expectException(ReflectionException::class);

        $reflector->reflectClass(__NAMESPACE__ . '\\DefinitelyMissingClass');
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDoesNotNormalizeAnInvalidDoubleLeadingSeparatorIntoACacheHit(): void
    {
        $reflector = new Reflector();

        $reflector->reflectClass(ReflectionFixture::class);

        $this->expectException(ReflectionException::class);

        $reflector->reflectClass('\\\\' . ReflectionFixture::class);
    }

    public function testRejectsAUnitEnumCaseAsABackedCase(): void
    {
        $reflector = new Reflector();

        $this->expectException(ReflectionException::class);

        $reflector->reflectEnumBackedCase(UnitStatus::class, 'Ready');
    }

    public function testPropagatesNativeFailureForAMissingZendExtension(): void
    {
        $reflector = new Reflector();

        $this->expectException(ReflectionException::class);

        $reflector->reflectZendExtension('super-kernel-missing-zend-extension');
    }
}
