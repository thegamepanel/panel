<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture implementation of {@see AbstractInterface} whose constructor requires an
 * instance of itself. Bound as the concrete class of the interface and aliased to it, it
 * supports testing that stack entries use the class after alias normalisation.
 */
class SelfDependentConcrete implements AbstractInterface
{
    public function __construct(
        public readonly SelfDependentConcrete $self,
    ) {
    }
}
