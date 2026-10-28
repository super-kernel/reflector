<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Tests\Fixture;

final class ReflectionFixture
{
    public const string LABEL = 'fixture';

    public string $property = 'value';

    public function method(string $parameter): string
    {
        return $parameter;
    }

    public function __invoke(string $parameter): string
    {
        return $parameter;
    }
}
