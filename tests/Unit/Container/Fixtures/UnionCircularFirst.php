<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class whose constructor requires {@see UnionCircularSecond} through a union
 * with a scalar, so the dependency is resolved by the union path of the generic
 * resolver. The second class requires this one in turn.
 */
class UnionCircularFirst
{
    public function __construct(
        public readonly string|UnionCircularSecond $second,
    ) {
    }
}
