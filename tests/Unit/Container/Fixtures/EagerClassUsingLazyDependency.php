<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class whose constructor reads a property of its {@see Lazy} dependency,
 * initialising the proxy while this class is still being constructed. The dependency
 * requires this class, so the read is a circular dependency.
 *
 * @see LazyClassDependingOnEager
 */
class EagerClassUsingLazyDependency
{
    public readonly EagerClassUsingLazyDependency $back;

    public function __construct(
        public readonly LazyClassDependingOnEager $dependency,
    ) {
        $this->back = $dependency->eager;
    }
}
