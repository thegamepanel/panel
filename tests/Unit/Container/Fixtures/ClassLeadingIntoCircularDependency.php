<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class whose constructor requires {@see MutualDependencyFirst}, so it leads
 * into a circular dependency without being part of it.
 */
class ClassLeadingIntoCircularDependency
{
    public function __construct(
        public readonly MutualDependencyFirst $first,
    ) {
    }
}
