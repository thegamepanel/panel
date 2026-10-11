<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A class with a private property, the parent of {@see ChildOfParentWithPrivateProperty}.
 */
class ParentWithPrivateProperty
{
    private string $value = 'initialized';

    public function value(): string
    {
        return $this->value;
    }
}
