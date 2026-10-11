<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Lazy;

/**
 * A fixture class decorated with {@see Lazy} whose constructor requires the class that
 * initialises its proxy from inside its own constructor.
 *
 * @see EagerClassUsingLazyDependency
 */
#[Lazy]
class LazyClassDependingOnEager
{
    public function __construct(
        public readonly EagerClassUsingLazyDependency $eager,
    ) {
    }
}
