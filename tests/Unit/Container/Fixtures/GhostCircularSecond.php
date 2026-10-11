<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class created as a ghost by {@see GhostCircularFirst}, whose constructor
 * requires that class.
 */
class GhostCircularSecond
{
    public function __construct(
        public readonly GhostCircularFirst $first,
    ) {
    }
}
