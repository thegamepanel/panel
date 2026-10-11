<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class resolved through a named binding, whose constructor requires
 * {@see NamedCircularFirst}.
 */
class NamedCircularSecond
{
    public function __construct(
        public readonly NamedCircularFirst $first,
    ) {
    }
}
