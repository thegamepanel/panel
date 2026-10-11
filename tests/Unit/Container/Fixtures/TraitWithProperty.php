<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A trait with a property, which PHP cannot make a lazy proxy for.
 */
trait TraitWithProperty
{
    public string $value = 'initialized';
}
