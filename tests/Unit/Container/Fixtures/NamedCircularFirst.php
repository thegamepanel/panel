<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Named;

/**
 * A fixture class whose constructor requires the 'inner' named binding of
 * {@see NamedCircularSecond}, which requires this class in turn. Used to test that a
 * named entry is reported with its name.
 */
class NamedCircularFirst
{
    public function __construct(
        #[Named('inner')] public readonly NamedCircularSecond $second,
    ) {
    }
}
