<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Lazy;

/**
 * The second of two fixture classes decorated with {@see Lazy} whose constructors require
 * each other.
 *
 * @see LazyCircularFirst
 */
#[Lazy]
class LazyCircularSecond
{
    public function __construct(
        public readonly LazyCircularFirst $first,
    ) {
    }
}
