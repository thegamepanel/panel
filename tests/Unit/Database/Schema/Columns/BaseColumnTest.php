<?php
declare(strict_types=1);

namespace Tests\Unit\Database\Schema\Columns;

use Engine\Database\Contracts\Query;
use Engine\Database\Exceptions\InvalidSchemaException;
use Engine\Database\Schema\Column;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Database\Schema\Fixtures\StatusEnum;

#[Group('unit'), Group('database'), Group('base-column')]
class BaseColumnTest extends TestCase
{
    #[Test]
    public function nullableModifier(): void
    {
        $column = Column::int('age')->nullable();

        $this->assertSame('`age` INT NULL', $column->toSql());
    }

    #[Test]
    public function notNullModifier(): void
    {
        $column = Column::int('age')->notNull();

        $this->assertSame('`age` INT NOT NULL', $column->toSql());
    }

    #[Test]
    public function nullableOverridesNotNull(): void
    {
        $column = Column::int('age')->notNull()->nullable();

        $this->assertSame('`age` INT NULL', $column->toSql());
    }

    #[Test]
    public function notNullOverridesNullable(): void
    {
        $column = Column::int('age')->nullable()->notNull();

        $this->assertSame('`age` INT NOT NULL', $column->toSql());
    }

    #[Test]
    public function commentModifier(): void
    {
        $column = Column::varchar('name')->comment('The user name');

        $this->assertSame("`name` VARCHAR COMMENT 'The user name'", $column->toSql());
    }

    #[Test]
    public function defaultNull(): void
    {
        $column = Column::varchar('name')->default(null);

        $this->assertSame('`name` VARCHAR DEFAULT NULL', $column->toSql());
    }

    #[Test]
    public function defaultStringValue(): void
    {
        $column = Column::varchar('status')->default('active');

        $this->assertSame("`status` VARCHAR DEFAULT 'active'", $column->toSql());
    }

    #[Test]
    public function defaultIntValue(): void
    {
        $column = Column::int('age')->default(42);

        $this->assertSame('`age` INT DEFAULT 42', $column->toSql());
    }

    #[Test]
    public function defaultFloatValue(): void
    {
        $column = Column::int('score')->default(9.99);

        $this->assertSame('`score` INT DEFAULT 9.99', $column->toSql());
    }

    #[Test]
    public function defaultBoolTrueValue(): void
    {
        $column = Column::int('active')->default(true);

        $this->assertSame('`active` INT DEFAULT 1', $column->toSql());
    }

    #[Test]
    public function defaultBoolFalseValue(): void
    {
        $column = Column::int('active')->default(false);

        $this->assertSame('`active` INT DEFAULT 0', $column->toSql());
    }

    #[Test]
    public function afterDoesNotAppearInSql(): void
    {
        $column = Column::int('age')->after('name');

        $this->assertSame('`age` INT', $column->toSql());
    }

    #[Test]
    public function firstDoesNotAppearInSql(): void
    {
        $column = Column::int('id')->first();

        $this->assertSame('`id` INT', $column->toSql());
    }

    #[Test]
    public function nullableWithDefault(): void
    {
        $column = Column::varchar('name')->nullable()->default('unknown');

        $this->assertSame("`name` VARCHAR NULL DEFAULT 'unknown'", $column->toSql());
    }

    #[Test]
    public function notNullWithDefault(): void
    {
        $column = Column::varchar('name')->notNull()->default('unknown');

        $this->assertSame("`name` VARCHAR NOT NULL DEFAULT 'unknown'", $column->toSql());
    }

    #[Test]
    public function notNullWithDefaultAndComment(): void
    {
        $column = Column::varchar('name')->notNull()->default('unknown')->comment('Full name');

        $this->assertSame("`name` VARCHAR NOT NULL DEFAULT 'unknown' COMMENT 'Full name'", $column->toSql());
    }

    #[Test]
    public function generatedColumnWithVirtualStorage(): void
    {
        $query = $this->createStubQuery('col_a + col_b');

        $column = Column::int('total')->generated($query)->virtual();

        $this->assertSame('`total` INT GENERATED ALWAYS AS (col_a + col_b) VIRTUAL', $column->toSql());
    }

    #[Test]
    public function generatedColumnWithStoredStorage(): void
    {
        $query = $this->createStubQuery('col_a * col_b');

        $column = Column::int('product')->generated($query)->stored();

        $this->assertSame('`product` INT GENERATED ALWAYS AS (col_a * col_b) STORED', $column->toSql());
    }

    #[Test]
    public function generatedColumnWithoutStorageType(): void
    {
        $query = $this->createStubQuery('col_a + col_b');

        $column = Column::int('total')->generated($query);

        $this->assertSame('`total` INT GENERATED ALWAYS AS (col_a + col_b)', $column->toSql());
    }

    #[Test]
    public function generatedColumnOmitsNullAndDefault(): void
    {
        $query = $this->createStubQuery('col_a + 1');

        $column = Column::int('computed')->nullable()->default(0)->generated($query)->virtual();

        $this->assertSame('`computed` INT GENERATED ALWAYS AS (col_a + 1) VIRTUAL', $column->toSql());
    }

    #[Test]
    public function virtualWithoutGeneratedThrowsException(): void
    {
        $this->expectException(InvalidSchemaException::class);

        Column::int('age')->virtual();
    }

    #[Test]
    public function storedWithoutGeneratedThrowsException(): void
    {
        $this->expectException(InvalidSchemaException::class);

        Column::int('age')->stored();
    }

    #[Test]
    public function getBindingsReturnsEmptyArray(): void
    {
        $column = Column::int('age');

        $this->assertSame([], $column->getBindings());
    }

    #[Test]
    public function defaultWithJsonSerializableEncodesAsJson(): void
    {
        $obj = new class implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['key' => 'value'];
            }
        };

        $column = Column::varchar('data', 255)->default($obj);

        $this->assertSame(
            '`data` VARCHAR(255) DEFAULT {"key":"value"}',
            $column->toSql(),
        );
    }

    #[Test]
    public function defaultWithArrayValue(): void
    {
        $column = Column::json('data')->default(['key' => 'value']);

        $this->assertSame('`data` JSON DEFAULT {"key":"value"}', $column->toSql());
    }

    // -------------------------------------------------------------------------
    // Unique constraint
    // -------------------------------------------------------------------------

    #[Test]
    public function uniqueAppendsUniqueToSql(): void
    {
        $column = Column::int('email_hash')->unique();

        $this->assertSame('`email_hash` INT UNIQUE', $column->toSql());
    }

    #[Test]
    public function uniqueWithOtherModifiers(): void
    {
        $column = Column::varchar('email', 255)->notNull()->unique()->comment('Unique email');

        $this->assertSame("`email` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Unique email'", $column->toSql());
    }

    // -------------------------------------------------------------------------
    // BackedEnum default
    // -------------------------------------------------------------------------

    #[Test]
    public function defaultWithBackedEnumUsesBackedValue(): void
    {
        $column = Column::varchar('status', 255)->default(StatusEnum::Active);

        $this->assertSame("`status` VARCHAR(255) DEFAULT 'active'", $column->toSql());
    }

    /**
     * Create a stub Query implementation for generated column tests.
     *
     * @param string $sql
     *
     * @return Query
     */
    private function createStubQuery(string $sql): Query
    {
        return new class($sql) implements Query {
            public function __construct(private readonly string $sql)
            {
            }

            public function toSql(): string
            {
                return $this->sql;
            }

            public function getBindings(): array
            {
                return [];
            }
        };
    }
}
