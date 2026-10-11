<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A class with no properties of its own whose parent has a private one, which
 * ReflectionClass::getProperties() does not list but PHP still defers in a lazy proxy.
 */
class ChildOfParentWithPrivateProperty extends ParentWithPrivateProperty
{
}
