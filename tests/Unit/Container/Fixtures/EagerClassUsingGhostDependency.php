<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Ghost;

/**
 * A fixture class whose constructor reads a property of its {@see Ghost} dependency,
 * initialising the ghost while this class is still being constructed. The dependency
 * requires this class, so the read is a circular dependency.
 *
 * @see GhostDependingOnEager
 */
class EagerClassUsingGhostDependency
{
    public readonly EagerClassUsingGhostDependency $back;

    public function __construct(
        #[Ghost] public readonly GhostDependingOnEager $dependency,
    ) {
        $this->back = $dependency->eager;
    }
}
