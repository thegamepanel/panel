<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A concrete implementation of {@see AbstractInterface} with a typed property, so a lazy
 * resolution of the interface through a binding can be given a real proxy of this class.
 */
class ConcreteClassWithProperty implements AbstractInterface
{
    public string $value;

    public function __construct()
    {
        $this->value = 'initialized';
    }
}
