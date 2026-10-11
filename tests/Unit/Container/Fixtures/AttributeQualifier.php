<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

use Attribute;
use Engine\Container\Contracts\Qualifier;

/**
 * A minimal qualifier that is also a PHP attribute, allowing it to be applied
 * directly to constructor parameters. Used to test that auto-wiring detects
 * qualifier attributes via IS_INSTANCEOF and that a parameter bearing both a
 * {@see Named} and a qualifier attribute is rejected by the container.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class AttributeQualifier implements Qualifier
{
}
