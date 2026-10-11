<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Lazy;

/**
 * The first of two fixture classes decorated with {@see Lazy} whose constructors require
 * each other, to support testing that deferred construction breaks a circular dependency.
 */
#[Lazy]
class LazyCircularFirst
{
    public function __construct(
        public readonly LazyCircularSecond $second,
    ) {
    }
}
