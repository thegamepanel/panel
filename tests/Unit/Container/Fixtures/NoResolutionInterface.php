<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\NoResolution;

/**
 * An abstract carrying #[NoResolution], used to test that the attribute applies when it
 * is on the requested class rather than the binding's concrete class.
 */
#[NoResolution]
interface NoResolutionInterface
{
}
