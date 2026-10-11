<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use ArrayObject;

/**
 * A class extending an internal PHP class, which PHP cannot make a lazy proxy for even
 * though the class itself is user-defined and has a property.
 *
 * @extends ArrayObject<array-key, mixed>
 */
class ClassExtendingInternalClass extends ArrayObject
{
    public string $value = 'initialized';
}
