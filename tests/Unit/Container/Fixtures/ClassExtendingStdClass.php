<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use stdClass;

/**
 * A class extending stdClass, the one internal class PHP can make a lazy proxy for a
 * subclass of.
 */
class ClassExtendingStdClass extends stdClass
{
    public string $value;

    public function __construct()
    {
        $this->value = 'initialized';
    }
}
