<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * The first of two fixture classes whose constructors require each other, to support
 * testing that the container detects a circular dependency through one other class.
 */
class MutualDependencyFirst
{
    public function __construct(
        public readonly MutualDependencySecond $second,
    ) {
    }
}
