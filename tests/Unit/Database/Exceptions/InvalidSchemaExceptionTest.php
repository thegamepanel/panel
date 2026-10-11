<?php
declare(strict_types=1);

namespace Tests\Unit\Database\Exceptions;

use Engine\Database\Exceptions\InvalidSchemaException;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit'), Group('database'), Group('invalid-schema-exception')]
class InvalidSchemaExceptionTest extends TestCase
{
    #[Test]
    public function incompatibleModifierContainsModifierAndType(): void
    {
        $exception = InvalidSchemaException::incompatibleModifier('precision', 'DATE');

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertSame(
            'The modifier "precision" is not compatible with the type "DATE"',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function requiresGeneratedColumnContainsModifier(): void
    {
        $exception = InvalidSchemaException::requiresGeneratedColumn('virtual');

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertSame(
            'The modifier "virtual" can only be used on a generated column',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function incompleteForeignKeyContainsMissingClause(): void
    {
        $exception = InvalidSchemaException::incompleteForeignKey('references');

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertSame(
            'Foreign key is missing a "references" clause',
            $exception->getMessage(),
        );
    }
}
