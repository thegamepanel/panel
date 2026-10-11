<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class resolved through a union-typed parameter of
 * {@see UnionCircularFirst}, whose constructor requires that class.
 */
class UnionCircularSecond
{
    public function __construct(
        public readonly UnionCircularFirst $first,
    ) {
    }
}
