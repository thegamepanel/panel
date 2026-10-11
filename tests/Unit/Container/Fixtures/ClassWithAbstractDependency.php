<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class whose constructor requires {@see AbstractInterface}. Taken as a
 * parameter by a factory binding of that interface, it supports testing that a factory
 * whose parameters require its own abstract is a circular dependency.
 */
class ClassWithAbstractDependency
{
    public function __construct(
        public readonly AbstractInterface $dependency,
    ) {
    }
}
