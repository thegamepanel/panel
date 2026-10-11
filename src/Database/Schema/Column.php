<?php
declare(strict_types=1);

namespace Engine\Database\Schema;

use BackedEnum;
use Engine\Database\Schema\Columns\BinaryColumn;
use Engine\Database\Schema\Columns\BooleanColumn;
use Engine\Database\Schema\Columns\DecimalColumn;
use Engine\Database\Schema\Columns\EnumColumn;
use Engine\Database\Schema\Columns\IntColumn;
use Engine\Database\Schema\Columns\JsonColumn;
use Engine\Database\Schema\Columns\StringColumn;
use Engine\Database\Schema\Columns\TemporalColumn;

/**
 * Column
 * ------
 *
 * Static factory for creating column definitions. Delegates to the
 * concrete column classes.
 */
final readonly class Column
{
    /**
     * Create a new BINARY column.
     *
     * @param string $name
     *
     * @return BinaryColumn<BinaryColumn::BINARY>
     */
    public static function binary(string $name): BinaryColumn
    {
        return BinaryColumn::binary($name);
    }

    /**
     * Create a new VARBINARY column.
     *
     * @param string $name
     *
     * @return BinaryColumn<BinaryColumn::VARBINARY>
     */
    public static function varbinary(string $name): BinaryColumn
    {
        return BinaryColumn::varbinary($name);
    }

    /**
     * Create a new TINYBLOB column.
     *
     * @param string $name
     *
     * @return BinaryColumn<BinaryColumn::TINYBLOB>
     */
    public static function tinyblob(string $name): BinaryColumn
    {
        return BinaryColumn::tinyblob($name);
    }

    /**
     * Create a new BLOB column.
     *
     * @param string $name
     *
     * @return BinaryColumn<BinaryColumn::BLOB>
     */
    public static function blob(string $name): BinaryColumn
    {
        return BinaryColumn::blob($name);
    }

    /**
     * Create a new MEDIUMBLOB column.
     *
     * @param string $name
     *
     * @return BinaryColumn<BinaryColumn::MEDIUMBLOB>
     */
    public static function mediumblob(string $name): BinaryColumn
    {
        return BinaryColumn::mediumblob($name);
    }

    /**
     * Create a new LONGBLOB column.
     *
     * @param string $name
     *
     * @return BinaryColumn<BinaryColumn::LONGBLOB>
     */
    public static function longblob(string $name): BinaryColumn
    {
        return BinaryColumn::longblob($name);
    }

    /**
     * Create a new DECIMAL column.
     *
     * @param string   $name
     * @param int|null $length
     * @param int|null $decimals
     *
     * @return DecimalColumn<DecimalColumn::DECIMAL>
     */
    public static function decimal(string $name, ?int $length = null, ?int $decimals = null): DecimalColumn
    {
        return DecimalColumn::decimal($name, $length, $decimals);
    }

    /**
     * Create a new FLOAT column.
     *
     * @param string   $name
     * @param int|null $length
     * @param int|null $decimals
     *
     * @return DecimalColumn<DecimalColumn::FLOAT>
     */
    public static function float(string $name, ?int $length = null, ?int $decimals = null): DecimalColumn
    {
        return DecimalColumn::float($name, $length, $decimals);
    }

    /**
     * Create a new DOUBLE column.
     *
     * @param string   $name
     * @param int|null $length
     * @param int|null $decimals
     *
     * @return DecimalColumn<DecimalColumn::DOUBLE>
     */
    public static function double(string $name, ?int $length = null, ?int $decimals = null): DecimalColumn
    {
        return DecimalColumn::double($name, $length, $decimals);
    }

    /**
     * Create a new ENUM column.
     *
     * @param string                                 $name
     * @param array<string>|class-string<BackedEnum> $values
     *
     * @return EnumColumn<EnumColumn::ENUM>
     */
    public static function enum(string $name, array|string $values): EnumColumn
    {
        return EnumColumn::enum($name, $values);
    }

    /**
     * Create a new SET column.
     *
     * @param string                                 $name
     * @param array<string>|class-string<BackedEnum> $values
     *
     * @return EnumColumn<EnumColumn::SET>
     */
    public static function set(string $name, array|string $values): EnumColumn
    {
        return EnumColumn::set($name, $values);
    }

    /**
     * Create a new TINYINT column.
     *
     * @param string   $name
     * @param int|null $length
     *
     * @return IntColumn<IntColumn::TINY>
     */
    public static function tinyInt(string $name, ?int $length = null): IntColumn
    {
        return IntColumn::tiny($name, $length);
    }

    /**
     * Create a new SMALLINT column.
     *
     * @param string   $name
     * @param int|null $length
     *
     * @return IntColumn<IntColumn::SMALL>
     */
    public static function smallInt(string $name, ?int $length = null): IntColumn
    {
        return IntColumn::small($name, $length);
    }

    /**
     * Create a new MEDIUMINT column.
     *
     * @param string   $name
     * @param int|null $length
     *
     * @return IntColumn<IntColumn::MEDIUM>
     */
    public static function mediumInt(string $name, ?int $length = null): IntColumn
    {
        return IntColumn::medium($name, $length);
    }

    /**
     * Create a new INT column.
     *
     * @param string   $name
     * @param int|null $length
     *
     * @return IntColumn<IntColumn::DEFAULT>
     */
    public static function int(string $name, ?int $length = null): IntColumn
    {
        return IntColumn::int($name, $length);
    }

    /**
     * Create a new BIGINT column.
     *
     * @param string   $name
     * @param int|null $length
     *
     * @return IntColumn<IntColumn::BIG>
     */
    public static function bigInt(string $name, ?int $length = null): IntColumn
    {
        return IntColumn::big($name, $length);
    }

    /**
     * Create a new BOOLEAN column.
     *
     * @param string $name
     *
     * @return BooleanColumn
     */
    public static function boolean(string $name): BooleanColumn
    {
        return BooleanColumn::make($name);
    }

    /**
     * Create a new JSON column.
     *
     * @param string $name
     *
     * @return JsonColumn
     */
    public static function json(string $name): JsonColumn
    {
        return JsonColumn::make($name);
    }

    /**
     * Create a new CHAR column.
     *
     * @param string   $name
     * @param int|null $length
     *
     * @return StringColumn<StringColumn::CHAR>
     */
    public static function char(string $name, ?int $length = null): StringColumn
    {
        return StringColumn::char($name, $length);
    }

    /**
     * Create a new VARCHAR column.
     *
     * @param string   $name
     * @param int|null $length
     *
     * @return StringColumn<StringColumn::VARCHAR>
     */
    public static function varchar(string $name, ?int $length = null): StringColumn
    {
        return StringColumn::varchar($name, $length);
    }

    /**
     * Create a new TINYTEXT column.
     *
     * @param string $name
     *
     * @return StringColumn<StringColumn::TINYTEXT>
     */
    public static function tinytext(string $name): StringColumn
    {
        return StringColumn::tinytext($name);
    }

    /**
     * Create a new TEXT column.
     *
     * @param string   $name
     * @param int|null $length
     *
     * @return StringColumn<StringColumn::TEXT>
     */
    public static function text(string $name, ?int $length = null): StringColumn
    {
        return StringColumn::text($name, $length);
    }

    /**
     * Create a new MEDIUMTEXT column.
     *
     * @param string $name
     *
     * @return StringColumn<StringColumn::MEDIUMTEXT>
     */
    public static function mediumtext(string $name): StringColumn
    {
        return StringColumn::mediumtext($name);
    }

    /**
     * Create a new LONGTEXT column.
     *
     * @param string $name
     *
     * @return StringColumn<StringColumn::LONGTEXT>
     */
    public static function longtext(string $name): StringColumn
    {
        return StringColumn::longtext($name);
    }

    /**
     * Create a new DATE column.
     *
     * @param string $name
     *
     * @return TemporalColumn<TemporalColumn::DATE>
     */
    public static function date(string $name): TemporalColumn
    {
        return TemporalColumn::date($name);
    }

    /**
     * Create a new DATETIME column.
     *
     * @param string $name
     *
     * @return TemporalColumn<TemporalColumn::DATETIME>
     */
    public static function datetime(string $name): TemporalColumn
    {
        return TemporalColumn::datetime($name);
    }

    /**
     * Create a new TIMESTAMP column.
     *
     * @param string $name
     *
     * @return TemporalColumn<TemporalColumn::TIMESTAMP>
     */
    public static function timestamp(string $name): TemporalColumn
    {
        return TemporalColumn::timestamp($name);
    }

    /**
     * Create a new TIME column.
     *
     * @param string $name
     *
     * @return TemporalColumn<TemporalColumn::TIME>
     */
    public static function time(string $name): TemporalColumn
    {
        return TemporalColumn::time($name);
    }

    /**
     * Create a new YEAR column.
     *
     * @param string $name
     *
     * @return TemporalColumn<TemporalColumn::YEAR>
     */
    public static function year(string $name): TemporalColumn
    {
        return TemporalColumn::year($name);
    }
}
