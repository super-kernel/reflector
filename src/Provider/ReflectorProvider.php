<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Provider;

use Closure;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\ReflectorInterface;
use SuperKernel\Reflector\Facade\ReflectorFacade;
use SuperKernel\Reflector\Reflector;

#[FactoryProvider(ReflectorInterface::class)]
final readonly class ReflectorProvider
{
    public function __invoke(): ReflectorInterface
    {
        $reflector = new Reflector();

        Closure::bind(
            static function (ReflectorInterface $reflector): void {
                ReflectorFacade::$reflector = $reflector;
            },
            null,
            ReflectorFacade::class,
        )($reflector);

        return $reflector;
    }
}
