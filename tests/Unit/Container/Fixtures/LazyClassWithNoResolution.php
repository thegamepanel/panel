<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Lazy;
use Engine\Container\Attributes\NoResolution;

/**
 * A class carrying both #[Lazy] and #[NoResolution], used to test that #[NoResolution] is
 * checked before a lazy proxy is returned.
 */
#[Lazy, NoResolution]
class LazyClassWithNoResolution
{
}
