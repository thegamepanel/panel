<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A resolvable class carrying only an attribute the container does not track.
 */
#[ThrowingAttribute]
class ClassWithThrowingAttribute
{
}
