<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class whose constructor requires an instance of itself, to support testing
 * that the container reports the shortest circular dependency rather than exhausting the
 * call stack.
 */
class SelfDependentClass
{
    public function __construct(
        public readonly SelfDependentClass $self,
    ) {
    }
}
