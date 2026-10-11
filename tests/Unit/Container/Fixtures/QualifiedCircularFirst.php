<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Fixtures;

/**
 * A fixture class whose constructor requires the {@see AttributeQualifier} qualified
 * binding of {@see QualifiedCircularSecond}, which requires this class in turn. Used to
 * test that a qualified entry is reported with its qualifier class.
 */
class QualifiedCircularFirst
{
    public function __construct(
        #[AttributeQualifier] public readonly QualifiedCircularSecond $second,
    ) {
    }
}
