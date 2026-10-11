<?php
declare(strict_types=1);

namespace Engine\Database\Schema\Columns;

use BackedEnum;
use Engine\Database\Contracts\Column;
use Engine\Database\Contracts\Expression;
use Engine\Database\Contracts\Query;
use Engine\Database\Exceptions\InvalidSchemaException;
use Engine\Database\Schema\Concerns\HasComment;
use Engine\Database\Schema\Concerns\HasDefault;
use JsonException;
use JsonSerializable;

/**
 * Base Column
 * -----------
 *
 * Provides an abstract base class for the shared functionality of the
 * individual column types.
 */
abstract class BaseColumn implements Column
{
    use HasDefault;
    use HasComment;

    /**
     * Flag for a virtual generated column.
     */
    private const int VIRTUAL = 1;

    /**
     * Flag for a stored generated column.
     */
    private const int STORED = 2;

    /**
     * The name of the column.
     *
     * @var string
     */
    public readonly string $name;

    /**
     * Whether the column is nullable.
     *
     * @var bool
     */
    protected bool $nullable = false;

    /**
     * Whether the column is not nullable.
     *
     * @var bool
     */
    protected bool $notNull = false;

    /**
     * The column this column should be added after.
     *
     * @var string|null
     */
    protected ?string $after = null;

    /**
     * Whether the column should be the first column in the table.
     *
     * @var bool
     */
    protected bool $first = false;

    /**
     * Whether the column should have a unique index.
     *
     * @var bool
     */
    protected bool $unique = false;

    /**
     * The expression to generate the value for the column.
     *
     * @var Query|null
     */
    protected ?Query $as = null;

    /**
     * The storage type of the generated column.
     *
     * @var self::VIRTUAL|self::STORED|null
     */
    protected ?int $storage = null;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Mark the column as nullable.
     *
     * @return static
     */
    public function nullable(): static
    {
        $this->nullable = true;
        $this->notNull  = false;

        return $this;
    }

    /**
     * Mark the column as not nullable.
     *
     * @return static
     */
    public function notNull(): static
    {
        $this->notNull  = true;
        $this->nullable = false;

        return $this;
    }

    /**
     * Add the column after another column.
     *
     * @param string $name
     *
     * @return static
     */
    public function after(string $name): static
    {
        $this->after = $name;

        return $this;
    }

    /**
     * Add the column as the first column in the table.
     *
     * @return static
     */
    public function first(): static
    {
        $this->first = true;

        return $this;
    }

    /**
     * Add a unique constraint to the column.
     *
     * @return static
     */
    public function unique(): static
    {
        $this->unique = true;

        return $this;
    }

    /**
     * Mark the column as generated, using the provided query.
     *
     * @param Query $query
     *
     * @return static
     */
    public function generated(Query $query): self
    {
        $this->as = $query;

        return $this;
    }

    /**
     * Mark a generated column as virtual.
     *
     * @return static
     */
    public function virtual(): self
    {
        if (! $this->isGenerated()) {
            throw InvalidSchemaException::requiresGeneratedColumn('virtual');
        }

        $this->storage = self::VIRTUAL;

        return $this;
    }

    /**
     * Mark a generated column as stored.
     *
     * @return static
     */
    public function stored(): self
    {
        if (! $this->isGenerated()) {
            throw InvalidSchemaException::requiresGeneratedColumn('stored');
        }

        $this->storage = self::STORED;

        return $this;
    }

    /**
     * Get the bindings for the expression.
     *
     * @return array<int|string, mixed>
     */
    public function getBindings(): array
    {
        return [];
    }

    /**
     * Get the SQL representation of the expression.
     *
     * @return string
     *
     * @throws JsonException
     */
    public function toSql(): string
    {
        // Start the definition with the name and the type definition.
        $sql = '`' . $this->name . '` ' . $this->getDefinition();

        if ($this->isGenerated()) {
            $this->addGeneratedSyntax($sql);
        } else {
            $this->addNullSyntax($sql);
            $this->addDefaultSyntax($sql);
        }

        // Some have a unique index/constraint.
        if ($this->unique) {
            $sql .= ' UNIQUE';
        }

        // Some may even have a comment.
        if ($this->hasComment()) {
            $sql .= ' COMMENT \'' . $this->getComment() . '\'';
        }

        return $sql;
    }

    /**
     * Determine whether the column is generated.
     *
     * @return bool
     *
     * @phpstan-assert-if-true !null $this->as
     */
    protected function isGenerated(): bool
    {
        return $this->as !== null;
    }

    /**
     * Get the definition of the column.
     *
     * @return string
     */
    abstract protected function getDefinition(): string;

    /**
     * Add the null syntax to the SQL.
     *
     * @param string $sql
     */
    private function addNullSyntax(string &$sql): void
    {
        // Set whether it's nullable or notnull.
        if ($this->notNull) {
            $sql .= ' NOT NULL';
        } else if ($this->nullable) {
            $sql .= ' NULL';
        }
    }

    /**
     * Add the default syntax to the SQL.
     *
     * @param string $sql
     *
     * @throws JsonException
     */
    private function addDefaultSyntax(string &$sql): void
    {
        // If it has a default value, add it.
        if ($this->hasDefault()) {
            $sql .= ' DEFAULT ';

            $default = $this->getDefault();

            // If the default value is a backed enum, we'll simplify it so it's
            // the actual backed value. That way it'll get caught in the logic
            // below without us worrying over whether it's a string or int.
            if ($default instanceof BackedEnum) {
                $default = $default->value;
            }

            // Default value is handled slightly differently depending on the
            // type of the value provided.
            if ($default === null) {
                $sql .= 'NULL';
            } else if ($default instanceof Expression) {
                $sql .= $default->toSql();
            } else if (is_string($default)) {
                // Strings need to be surrounded by single quotes.
                $sql .= '\'' . $default . '\'';
            } else if (is_int($default) || is_float($default)) {
                $sql .= $default;
            } else if (is_bool($default)) {
                // MySQL treats bools as ints.
                $sql .= $default ? '1' : '0';
            } else if (is_array($default) || $default instanceof JsonSerializable) {
                // Nice little QOL feature, even if the column isn't a JSON one.
                $sql .= json_encode($default, JSON_THROW_ON_ERROR);
            }
        }
    }

    /**
     * Add the generated syntax to the SQL.
     *
     * @param string $sql
     */
    private function addGeneratedSyntax(string &$sql): void
    {
        if ($this->isGenerated()) {
            $sql .= ' GENERATED ALWAYS AS (' . $this->as->toSql() . ')';

            if ($this->storage === self::STORED) {
                $sql .= ' STORED';
            } else if ($this->storage === self::VIRTUAL) {
                $sql .= ' VIRTUAL';
            }
        }
    }
}
