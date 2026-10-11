<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * An enum, which PHP cannot make a lazy proxy for.
 */
enum ExampleEnum
{
    case One;
}
