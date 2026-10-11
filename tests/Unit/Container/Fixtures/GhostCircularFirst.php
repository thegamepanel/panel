<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Ghost;

/**
 * A fixture class whose constructor requires {@see GhostCircularSecond} as a ghost, which
 * requires this class in turn. Used to test that a ghost breaks a circular dependency.
 */
class GhostCircularFirst
{
    public function __construct(
        #[Ghost] public readonly GhostCircularSecond $second,
    ) {
    }
}
