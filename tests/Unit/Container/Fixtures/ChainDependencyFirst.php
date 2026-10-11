<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * The first of three fixture classes whose constructors form a chain back to this one,
 * to support testing that a circular dependency is reported with every entry in order.
 */
class ChainDependencyFirst
{
    public function __construct(
        public readonly ChainDependencySecond $second,
    ) {
    }
}
