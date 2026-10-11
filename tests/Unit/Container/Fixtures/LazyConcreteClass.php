<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Engine\Container\Attributes\Lazy;

/**
 * A concrete implementation of {@see AbstractInterface} decorated with {@see Lazy}, to
 * support testing that the attribute applies when carried by a binding's concrete class
 * and the abstract is the class requested.
 */
#[Lazy]
class LazyConcreteClass implements AbstractInterface
{
    public string $value;

    public function __construct()
    {
        $this->value = 'initialized';
    }
}
