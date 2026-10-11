<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A class whose only property is virtual, having a get hook and no backing value, so a
 * lazy proxy has nothing to defer.
 */
class ClassWithVirtualProperty
{
    public string $value {
        get => 'virtual';
    }
}
