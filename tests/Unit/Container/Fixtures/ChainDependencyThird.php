<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * The third of three fixture classes whose constructors form a chain, closing it by
 * requiring the first.
 *
 * @see ChainDependencyFirst
 */
class ChainDependencyThird
{
    public function __construct(
        public readonly ChainDependencyFirst $first,
    ) {
    }
}
