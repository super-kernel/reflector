<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Tests\Unit;

use Error;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use SuperKernel\Contract\ReflectorInterface;
use SuperKernel\Reflector\Facade\ReflectorFacade;
use SuperKernel\Reflector\Provider\ReflectorProvider;
use SuperKernel\Reflector\Tests\Fixture\ReflectionFixture;
use Throwable;

final class ReflectorFacadeTest extends TestCase
{
    public function testExposesOnlyAStaticPublicDispatchMethod(): void
    {
        $facade = new ReflectionClass(ReflectorFacade::class);
        $methods = $facade->getMethods(ReflectionMethod::IS_PUBLIC);

        self::assertTrue($facade->isFinal());
        self::assertCount(1, $methods);
        self::assertSame('__callStatic', $methods[0]->getName());
        self::assertTrue($methods[0]->isStatic());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRejectsCallsBeforeProviderInitialization(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('ReflectorFacade requires initialization through ReflectorProvider.');

        ReflectorFacade::reflectClass(ReflectionFixture::class);
    }

    /**
     * Reversed named arguments deliberately verify forwarding by name rather than position.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpNamedArgumentsWithChangedOrderInspection
     */
    public function testForwardsNamedArgumentsAndTheSingleArgumentMethodForm(): void
    {
        $reflector = new ReflectorProvider()();
        $method = $reflector->reflectMethod(ReflectionFixture::class, 'method');

        self::assertSame($method, ReflectorFacade::reflectMethod(
            method: 'method',
            objectOrMethod: ReflectionFixture::class,
        ));
        self::assertSame($method, ReflectorFacade::reflectMethod(ReflectionFixture::class . '::method'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDispatchesThroughTheInjectedReflector(): void
    {
        $reflection = new ReflectionClass(ReflectionFixture::class);
        $reflector = $this->createMock(ReflectorInterface::class);
        $reflector->expects(self::once())
            ->method('reflectClass')
            ->with(ReflectionFixture::class)
            ->willReturn($reflection);
        $property = new ReflectionProperty(ReflectorFacade::class, 'reflector');
        $property->setValue(null, $reflector);

        self::assertSame($reflection, ReflectorFacade::reflectClass(ReflectionFixture::class));
    }

    /** @param list<mixed> $arguments */
    #[DataProvider('invalidTargets')]
    public function testPropagatesNativeReflectionFailures(string $method, array $arguments, bool $facade): void
    {
        $reflector = new ReflectorProvider()();
        $this->expectException(ReflectionException::class);

        if ($facade) {
            ReflectorFacade::{$method}(...$arguments);
        } else {
            $reflector->{$method}(...$arguments);
        }
    }

    /** @return iterable<string, array{string, list<mixed>, bool}> */
    public static function invalidTargets(): iterable
    {
        foreach ([
            false,
            true,
        ] as $facade) {
            $entry = $facade ? 'facade' : 'instance';

            yield $entry . ' missing class' => [
                'reflectClass',
                [
                    __NAMESPACE__ . '\\DefinitelyMissingClass',
                ],
                $facade,
            ];
            yield $entry . ' missing reference' => [
                'reflectReference',
                [
                    [],
                    'missing',
                ],
                $facade,
            ];
            yield $entry . ' missing Zend extension' => [
                'reflectZendExtension',
                [
                    'super-kernel-missing-zend-extension',
                ],
                $facade,
            ];
        }
    }

    /**
     * Attempts a helper call that the reflector interface does not declare through the public facade.
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    public function testDoesNotExposeMethodsOutsideTheReflectorInterface(): void
    {
        new ReflectorProvider()();
        $this->expectException(Error::class);
        $this->expectExceptionMessageIsOrContains(
            'Call to undefined method SuperKernel\\Reflector\\Reflector::symbolKey()',
        );

        ReflectorFacade::symbolKey('Example');
    }
}
