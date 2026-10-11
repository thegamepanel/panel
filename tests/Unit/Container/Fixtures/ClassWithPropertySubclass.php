<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A subclass of {@see ClassWithProperty}, which PHP does not accept as the real instance
 * behind a lazy proxy of its parent.
 */
class ClassWithPropertySubclass extends ClassWithProperty
{
}
