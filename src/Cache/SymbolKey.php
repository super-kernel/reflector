<?php

declare(strict_types=1);

namespace SuperKernel\Reflector\Cache;

use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Derives case-insensitive cache keys from symbol names without one leading namespace separator.
 *
 * @internal
 */
final class SymbolKey
{
    public static function fromName(string $symbol): string
    {
        if (str_starts_with($symbol, '\\')) {
            $symbol = substr($symbol, 1);
        }

        return strtolower($symbol);
    }
}
