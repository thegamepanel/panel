<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * An abstract class with a property, so it fails only the abstract check and not the
 * check for properties a lazy proxy could defer.
 */
abstract class AbstractClassWithProperty
{
    public string $value = 'initialized';
}
