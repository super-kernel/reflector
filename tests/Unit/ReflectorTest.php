<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Tests\Unit;

use Fiber;
use Generator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SuperKernel\Contract\ReflectorInterface;
use SuperKernel\Reflector\Facade\ReflectorFacade;
use SuperKernel\Reflector\Provider\ReflectorProvider;
use SuperKernel\Reflector\Reflector;
use SuperKernel\Reflector\Tests\Fixture\BackedStatus;
use SuperKernel\Reflector\Tests\Fixture\ReflectionFixture;
use SuperKernel\Reflector\Tests\Fixture\UnitStatus;
use Throwable;
use WeakReference;

use function get_loaded_extensions;

final class ReflectorTest extends TestCase
{
    public function testImplementsEveryReflectionEntryPointAsAnInstanceMethod(): void
    {
        $reflector = new ReflectionClass(Reflector::class);

        self::assertTrue($reflector->isFinal());
        self::assertFalse($reflector->isReadOnly());
        self::assertTrue($reflector->isInstantiable());
        self::assertTrue($reflector->implementsInterface(ReflectorInterface::class));
        self::assertNull($reflector->getConstructor());
        self::assertNotEmpty($reflector->getProperties());

        foreach ($reflector->getMethods() as $method) {
            self::assertFalse($method->isStatic(), $method->getName() . '() must be an instance method.');
        }

        foreach ($reflector->getProperties() as $property) {
            self::assertTrue($property->isPrivate());
            self::assertFalse($property->isStatic(), $property->getName() . ' must belong to the instance.');
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCachesAreIsolatedBetweenInstancesAndSharedWithTheFacade(): void
    {
        $reflector = new Reflector();
        $provided = new ReflectorProvider()();
        $object = new ReflectionFixture();
        $value = 'value';
        $references = [
            'value' => &$value,
        ];
        $generator = (static function (): Generator {
            yield 'value';
        })();
        $fiber = new Fiber(static function (): void {
        });
        $targets = [
            'reflectClass' => [
                ReflectionFixture::class,
            ],
            'reflectClassConstant' => [
                $object,
                'LABEL',
            ],
            'reflectConstant' => [
                'PHP_VERSION',
            ],
            'reflectEnum' => [
                UnitStatus::Ready,
            ],
            'reflectEnumUnitCase' => [
                UnitStatus::class,
                'Ready',
            ],
            'reflectEnumBackedCase' => [
                BackedStatus::Ready,
                'Ready',
            ],
            'reflectExtension' => [
                'Reflection',
            ],
            'reflectFunction' => [
                static fn(string $value): string => $value,
            ],
            'reflectMethod' => [
                $object,
                'method',
            ],
            'reflectParameter' => [
                $object,
                'parameter',
            ],
            'reflectObject' => [
                $object,
            ],
            'reflectProperty' => [
                $object,
                'property',
            ],
            'reflectGenerator' => [
                $generator,
            ],
            'reflectFiber' => [
                $fiber,
            ],
            'reflectReference' => [
                $references,
                'value',
            ],
        ];

        foreach ($targets as $method => $arguments) {
            $reflection = $reflector->{$method}(...$arguments);
            self::assertSame($reflection, $reflector->{$method}(...$arguments), $method);
            $providedReflection = $provided->{$method}(...$arguments);
            self::assertNotSame($reflection, $providedReflection, $method);
            self::assertSame($providedReflection, $provided->{$method}(...$arguments), $method);
            self::assertSame($providedReflection, ReflectorFacade::{$method}(...$arguments), $method);
        }

        self::assertSame(
            $reflector->reflectMethod($object, 'method'),
            $reflector->reflectMethod(ReflectionFixture::class . '::method'),
        );
        self::assertNull($reflector->reflectReference([
            'value' => $value,
        ], 'value'));
        self::assertNull(ReflectorFacade::reflectReference([
            'value' => $value,
        ], 'value'));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReusesLoadedZendExtensionReflections(): void
    {
        $extensions = get_loaded_extensions(true);

        if ([] === $extensions) {
            self::markTestSkipped('No Zend extension is loaded in this PHP runtime.');
        }

        $reflector = new Reflector();
        $provided = new ReflectorProvider()();

        foreach ($extensions as $extension) {
            $reflection = $reflector->reflectZendExtension($extension);
            self::assertSame($reflection, $reflector->reflectZendExtension($extension));
            $providedReflection = $provided->reflectZendExtension($extension);
            self::assertNotSame($reflection, $providedReflection);
            self::assertSame($providedReflection, $provided->reflectZendExtension($extension));
            self::assertSame($providedReflection, ReflectorFacade::reflectZendExtension($extension));
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReleasesItsCachedReflectionsWhenTheInstanceIsReleased(): void
    {
        $reflector = new Reflector();
        $reflection = $reflector->reflectClass(ReflectionFixture::class);
        $reference = WeakReference::create($reflection);
        unset($reflection);

        self::assertNotNull($reference->get());
        unset($reflector);
        self::assertNull($reference->get());
    }
}
