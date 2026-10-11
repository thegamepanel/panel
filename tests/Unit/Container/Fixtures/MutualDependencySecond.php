<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * The second of two fixture classes whose constructors require each other.
 *
 * @see MutualDependencyFirst
 */
class MutualDependencySecond
{
    public function __construct(
        public readonly MutualDependencyFirst $first,
    ) {
    }
}
