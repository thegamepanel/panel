<?php
declare(strict_types=1);

namespace Tests\Unit\Database\Schema\Columns;

use AssertionError;
use BackedEnum;
use Engine\Database\Schema\Column;
use Engine\Database\Schema\Columns\EnumColumn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Database\Schema\Fixtures\BasicEnum;
use Tests\Unit\Database\Schema\Fixtures\EmptyEnum;
use Tests\Unit\Database\Schema\Fixtures\IntBackedEnum;
use Tests\Unit\Database\Schema\Fixtures\StatusEnum;

#[Group('unit'), Group('database'), Group('enum-column')]
class EnumColumnTest extends TestCase
{
    // -------------------------------------------------------------------------
    // ENUM column
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('validValues')]
    public function enumColumnGeneratesCorrectSql(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::enum('status', $values);

        $this->assertSame("`status` ENUM({$expectedValuesSql})", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function enumColumnWithNullable(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::enum('status', $values)->nullable();

        $this->assertSame("`status` ENUM({$expectedValuesSql}) NULL", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function enumColumnWithNotNull(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::enum('status', $values)->notNull();

        $this->assertSame("`status` ENUM({$expectedValuesSql}) NOT NULL", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function enumColumnWithDefault(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::enum('status', $values)->default($firstValue);

        $this->assertSame("`status` ENUM({$expectedValuesSql}) DEFAULT '{$firstValue}'", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function enumColumnWithComment(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::enum('status', $values)->comment('User status');

        $this->assertSame("`status` ENUM({$expectedValuesSql}) COMMENT 'User status'", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function enumColumnWithCharsetAndCollation(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::enum('status', $values)
            ->charset('utf8mb4')
            ->collation('utf8mb4_unicode_ci')
        ;

        $this->assertSame("`status` ENUM({$expectedValuesSql}) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function enumColumnWithCharsetOnly(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::enum('status', $values)->charset('utf8mb4');

        $this->assertSame("`status` ENUM({$expectedValuesSql}) CHARACTER SET utf8mb4", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function enumColumnWithCollationOnly(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::enum('status', $values)->collation('utf8mb4_unicode_ci');

        $this->assertSame("`status` ENUM({$expectedValuesSql}) COLLATE utf8mb4_unicode_ci", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function enumFactoryMethodCreatesSameResult(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = Column::enum('status', $values);

        $this->assertInstanceOf(EnumColumn::class, $column);
        $this->assertSame("`status` ENUM({$expectedValuesSql})", $column->toSql());
    }

    // -------------------------------------------------------------------------
    // SET column
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('validValues')]
    public function setColumnGeneratesCorrectSql(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::set('permissions', $values);

        $this->assertSame("`permissions` SET({$expectedValuesSql})", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function setColumnWithDefault(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = EnumColumn::set('permissions', $values)->default($firstValue);

        $this->assertSame("`permissions` SET({$expectedValuesSql}) DEFAULT '{$firstValue}'", $column->toSql());
    }

    #[Test]
    #[DataProvider('validValues')]
    public function setFactoryMethodCreatesSameResult(array|string $values, string $expectedValuesSql, string $firstValue): void
    {
        $column = Column::set('permissions', $values);

        $this->assertInstanceOf(EnumColumn::class, $column);
        $this->assertSame("`permissions` SET({$expectedValuesSql})", $column->toSql());
    }
    // -------------------------------------------------------------------------
    // Data Providers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{array<string>|class-string<BackedEnum>, string, string}>
     */
    public static function validValues(): array
    {
        return [
            'string array'    => [['active', 'inactive'], "'active', 'inactive'", 'active'],
            'backed enum'     => [StatusEnum::class, "'active', 'inactive'", 'active'],
            'int backed enum' => [IntBackedEnum::class, "'200', '404'", '200'],
        ];
    }

    // -------------------------------------------------------------------------
    // Single value
    // -------------------------------------------------------------------------

    #[Test]
    public function enumColumnWithSingleValue(): void
    {
        $column = EnumColumn::enum('flag', ['yes']);

        $this->assertSame("`flag` ENUM('yes')", $column->toSql());
    }

    // -------------------------------------------------------------------------
    // Empty values
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('emptyValues')]
    public function enumRejectsEmptyValues(array|string $values): void
    {
        $this->expectException(AssertionError::class);

        EnumColumn::enum('status', $values);
    }

    #[Test]
    #[DataProvider('emptyValues')]
    public function setRejectsEmptyValues(array|string $values): void
    {
        $this->expectException(AssertionError::class);

        EnumColumn::set('status', $values);
    }

    /**
     * @return array<string, array{array<string>|class-string<BackedEnum>}>
     */
    public static function emptyValues(): array
    {
        return [
            'empty array'        => [[]],
            'enum without cases' => [EmptyEnum::class],
        ];
    }

    #[Test]
    public function enumRejectsNonBackedEnum(): void
    {
        $this->expectException(AssertionError::class);

        EnumColumn::enum('status', BasicEnum::class);
    }

    #[Test]
    public function setRejectsNonBackedEnum(): void
    {
        $this->expectException(AssertionError::class);

        EnumColumn::set('status', BasicEnum::class);
    }
}
