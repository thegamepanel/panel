<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class created as a ghost by the class whose constructor initialises it, and
 * whose own constructor requires that class.
 *
 * @see EagerClassUsingGhostDependency
 */
class GhostDependingOnEager
{
    public function __construct(
        public readonly EagerClassUsingGhostDependency $eager,
    ) {
    }
}
