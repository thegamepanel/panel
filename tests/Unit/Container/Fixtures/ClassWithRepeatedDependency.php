<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class whose constructor requires the same class for two parameters, to
 * support testing that resolving a class twice in sequence is not a circular dependency.
 */
class ClassWithRepeatedDependency
{
    public function __construct(
        public readonly ClassWithMethods $first,
        public readonly ClassWithMethods $second,
    ) {
    }
}
