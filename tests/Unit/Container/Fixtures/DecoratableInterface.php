<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture abstract bound to {@see DecoratingClass}, with an 'inner' named binding to
 * {@see DecoratedClass}. Used to test that a decorator depending on a named binding of
 * its own abstract is not a circular dependency.
 */
interface DecoratableInterface
{
}
