<?php
declare(strict_types=1);

namespace SuperKernel\Reflector\Provider;

use SuperKernel\Attribute\Factory;
use SuperKernel\Attribute\Provider;
use SuperKernel\Contract\ReflectionCollectorInterface;
use SuperKernel\Reflector\ReflectionCollector;

#[
	Provider(ReflectionCollectorInterface::class),
	Factory,
]
final class ReflectionCollectorProvider
{
	private static ReflectionCollectorInterface $reflectionCollector;

	public function __invoke(): ReflectionCollectorInterface
	{
		if (!isset(self::$reflectionCollector)) {
			self::$reflectionCollector = new ReflectionCollector();
		}

		return self::$reflectionCollector;
	}
}