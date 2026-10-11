<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class resolved through a qualified binding, whose constructor requires
 * {@see QualifiedCircularFirst}.
 */
class QualifiedCircularSecond
{
    public function __construct(
        public readonly QualifiedCircularFirst $first,
    ) {
    }
}
