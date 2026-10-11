<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Attribute;
use LogicException;

/**
 * A third-party class attribute whose constructor throws, used to prove that the
 * container reads class-level attributes without ever instantiating them.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ThrowingAttribute
{
    public function __construct()
    {
        throw new LogicException('ThrowingAttribute must never be instantiated.');
    }
}
