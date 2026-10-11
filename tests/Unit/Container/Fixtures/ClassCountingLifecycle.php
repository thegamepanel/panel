<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A class with no instance properties that counts how often its constructor and
 * destructor run, to show a lazy resolution of it is constructed rather than given an
 * object PHP marks as initialised without running the constructor.
 */
class ClassCountingLifecycle
{
    public static int $constructed = 0;

    public static int $destructed = 0;

    public function __construct()
    {
        ++self::$constructed;
    }

    public function __destruct()
    {
        ++self::$destructed;
    }
}
