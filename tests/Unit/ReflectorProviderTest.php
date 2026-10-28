<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Attribute\Provider;
use SuperKernel\Contract\ReflectorInterface;
use SuperKernel\Reflector\Facade\ReflectorFacade;
use SuperKernel\Reflector\Provider\ReflectorProvider;
use SuperKernel\Reflector\Reflector;
use SuperKernel\Reflector\Tests\Fixture\ReflectionFixture;
use Throwable;

final class ReflectorProviderTest extends TestCase
{
    public function testDeclaresTheSharedFactoryProviderContract(): void
    {
        $provider = new ReflectionClass(ReflectorProvider::class);
        $attributes = $provider->getAttributes(FactoryProvider::class);
        $reflector = new ReflectionClass(Reflector::class);

        self::assertTrue($provider->isFinal());
        self::assertTrue($provider->isReadOnly());
        self::assertNull($provider->getConstructor());
        self::assertFalse($provider->implementsInterface(ReflectorInterface::class));
        self::assertSame([], $provider->getAttributes(Provider::class));
        self::assertCount(1, $attributes);
        self::assertSame(ReflectorInterface::class, $attributes[0]->newInstance()->className);
        self::assertCount(1, $provider->getMethods(ReflectionMethod::IS_PUBLIC));
        $invoke = $provider->getMethod('__invoke');
        self::assertFalse($invoke->isStatic());
        self::assertSame(0, $invoke->getNumberOfParameters());
        self::assertSame(ReflectorInterface::class, (string) $invoke->getReturnType());
        self::assertTrue($reflector->implementsInterface(ReflectorInterface::class));
        self::assertSame([], $reflector->getAttributes(Provider::class));
        self::assertSame([], $reflector->getAttributes(FactoryProvider::class));
    }

    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testCreatesIndependentReflectionCaches(): void
    {
        $provider = new ReflectorProvider();
        $first = $provider();
        $second = $provider();
        $reflection = $first->reflectClass(ReflectionFixture::class);

        self::assertInstanceOf(Reflector::class, $first);
        self::assertInstanceOf(Reflector::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame($reflection, $first->reflectClass(ReflectionFixture::class));
        $secondReflection = $second->reflectClass(ReflectionFixture::class);
        self::assertNotSame($reflection, $secondReflection);
        self::assertSame($secondReflection, $second->reflectClass(ReflectionFixture::class));
        self::assertSame($secondReflection, ReflectorFacade::reflectClass(ReflectionFixture::class));
    }

    public function testBindsEachProducedInstanceToThePrivateStaticFacadeProperty(): void
    {
        $property = new ReflectionProperty(ReflectorFacade::class, 'reflector');
        self::assertTrue($property->isPrivate());
        self::assertTrue($property->isStatic());

        $first = new ReflectorProvider()();
        self::assertSame($first, $property->getValue());

        $second = new ReflectorProvider()();
        self::assertNotSame($first, $second);
        self::assertSame($second, $property->getValue());
    }
}
