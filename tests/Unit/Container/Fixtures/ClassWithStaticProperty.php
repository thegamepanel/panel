<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A class whose only property is static, so a lazy proxy has nothing to defer.
 */
class ClassWithStaticProperty
{
    public static string $value = 'static';
}
