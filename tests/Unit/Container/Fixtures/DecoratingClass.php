<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Named;

/**
 * A fixture decorator whose constructor requires the 'inner' named binding of its own
 * abstract, {@see DecoratableInterface}.
 */
class DecoratingClass implements DecoratableInterface
{
    public function __construct(
        #[Named('inner')] public readonly DecoratableInterface $inner,
    ) {
    }
}
