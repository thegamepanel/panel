<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Contracts\Qualifier;

/**
 * A qualifier carrying a string tag. Used to test that the container's qualified
 * instance cache keys on the qualifier's class alone, so two tags share one
 * instance and the tag is ignored.
 */
class TaggedQualifier implements Qualifier
{
    public function __construct(public readonly string $tag)
    {
    }
}
