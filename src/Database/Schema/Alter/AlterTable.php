<?php
declare(strict_types=1);

namespace Engine\Database\Schema\Alter;

use Engine\Database\Contracts\Column;
use Engine\Database\Contracts\Index;
use Engine\Database\Contracts\Schema;
use Engine\Database\Schema\Drop;
use InvalidArgumentException;

/**
 * Alter Table
 * -----------
 *
 * Schema builder for altering a table. Compiles into a single "ALTER TABLE"
 * statement.
 */
class AlterTable implements Schema
{
    /**
     * The name of the table being altered.
     *
     * @var string
     */
    public readonly string $table;

    /**
     * The new name of the table, if renaming.
     *
     * @var string|null
     */
    private ?string $newName = null;

    /**
     * New columns to be added to the table.
     *
     * @var array<Column>
     */
    private array $newColumns = [];

    /**
     * New indexes to be added to the table.
     *
     * @var array<Index>
     */
    private array $newIndexes = [];

    /**
     * Columns to be modified.
     *
     * @var array<Column>
     */
    private array $modifyColumns = [];

    /**
     * Columns to be dropped.
     *
     * @var array<Drop<'COLUMN'>>
     */
    private array $dropColumns = [];

    /**
     * Indexes to be dropped.
     *
     * @var array<Drop<'INDEX'|'FOREIGN KEY'>>
     */
    private array $dropIndexes = [];

    /**
     * Whether to drop the primary key.
     *
     * @var bool
     */
    private bool $dropPrimaryKey = false;

    /**
     * Columns to be renamed.
     *
     * @var array<string, string>
     */
    private array $renamedColumns = [];

    /**
     * @param string $table
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Rename the table.
     *
     * @param string $newName
     *
     * @return static
     */
    public function rename(string $newName): static
    {
        $this->newName = $newName;

        return $this;
    }

    /**
     * Add new columns or indexes to the table.
     *
     * @param Column|Index ...$new
     *
     * @return static
     */
    public function add(Column|Index ...$new): static
    {
        foreach ($new as $item) {
            if ($item instanceof Column) {
                $this->newColumns[] = $item;
            } else {
                $this->newIndexes[] = $item;
            }
        }

        return $this;
    }

    /**
     * Modify existing columns.
     *
     * @param Column ...$columns
     *
     * @return static
     */
    public function modify(Column ...$columns): static
    {
        $this->modifyColumns = array_merge($this->modifyColumns, $columns);

        return $this;
    }

    /**
     * Drop columns, indexes, primary keys, and foreign keys from the table.
     *
     * @param Drop<'COLUMN'|'INDEX'|'FOREIGN KEY'|'PRIMARY KEY'> ...$drops
     *
     * @return static
     */
    public function drop(Drop ...$drops): static
    {
        foreach ($drops as $drop) {
            if ($drop->isDroppingAColumn()) {
                /** @var Drop<'COLUMN'> $drop */
                $this->dropColumns[] = $drop;
            } else if ($drop->isDroppingThePrimaryKey()) {
                $this->dropPrimaryKey = true;
            } else if ($drop->isDroppingAnIndex()) {
                /** @var Drop<'INDEX'|'FOREIGN KEY'> $drop */
                $this->dropIndexes[] = $drop;
            } else {
                throw new InvalidArgumentException('Invalid drop type');
            }
        }

        return $this;
    }

    /**
     * Rename a column.
     *
     * @param string $column
     * @param string $newColumn
     *
     * @return static
     */
    public function move(string $column, string $newColumn): static
    {
        $this->renamedColumns[$column] = $newColumn;

        return $this;
    }

    /**
     * Get the SQL representation of the expression.
     *
     * The order that the individual parts are handled is intentional.
     * Situations may arise where there are multiple parts referencing the same
     * thing, whether a column to be removed and readded rather than modified.
     * The order is to help mitigate issues that arise from that, as well as
     * any other possibilities. The order is:
     *
     *   - Rename
     *   - Drop Primary Key
     *   - Drop Indexes
     *   - Drop Columns
     *   - Modify Columns
     *   - Rename Columns
     *   - Add Columns
     *   - Add Indexes
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = "ALTER TABLE `{$this->table}`";

        if ($this->newName) {
            $sql .= " RENAME TO `{$this->newName}`,";
        }

        if ($this->dropPrimaryKey) {
            $sql .= ' DROP PRIMARY KEY,';
        }

        if (! empty($this->dropIndexes)) {
            foreach ($this->dropIndexes as $drop) {
                $sql .= ' ' . $drop->toSql() . ',';
            }
        }

        if (! empty($this->dropColumns)) {
            foreach ($this->dropColumns as $drop) {
                $sql .= ' ' . $drop->toSql() . ',';
            }
        }

        if (! empty($this->modifyColumns)) {
            foreach ($this->modifyColumns as $column) {
                $sql .= ' MODIFY COLUMN ' . $column->toSql() . ',';
            }
        }

        if (! empty($this->renamedColumns)) {
            foreach ($this->renamedColumns as $old => $new) {
                $sql .= " RENAME COLUMN `{$old}` TO `{$new}`,";
            }
        }

        if (! empty($this->newColumns)) {
            foreach ($this->newColumns as $column) {
                $sql .= ' ADD COLUMN ' . $column->toSql() . ',';
            }
        }

        if (! empty($this->newIndexes)) {
            foreach ($this->newIndexes as $index) {
                $sql .= ' ADD ' . $index->toSql() . ',';
            }
        }

        return rtrim($sql, ',');
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
}
