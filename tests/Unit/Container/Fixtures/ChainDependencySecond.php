<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * The second of three fixture classes whose constructors form a chain.
 *
 * @see ChainDependencyFirst
 */
class ChainDependencySecond
{
    public function __construct(
        public readonly ChainDependencyThird $third,
    ) {
    }
}
