<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A class with a private constructor, which ReflectionClass::isInstantiable() reports as
 * not instantiable but PHP can still make a lazy proxy for.
 */
class ClassWithPrivateConstructor
{
    public static function make(): self
    {
        return new self();
    }

    public string $value;

    private function __construct()
    {
        $this->value = 'initialized';
    }
}
