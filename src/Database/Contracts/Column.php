<?php
declare(strict_types=1);

namespace Engine\Database\Contracts;

/**
 * Column Contract
 * ---------------
 *
 * Represents an SQL column definition as a part of a statement. Can be used
 * when creating and modifying columns.
 */
interface Column extends Expression
{
    /**
     * Mark the column as nullable.
     *
     * @return static
     */
    public function nullable(): static;

    /**
     * Mark the column as not nullable.
     *
     * @return static
     */
    public function notNull(): static;

    /**
     * Add a comment to the column.
     *
     * @param string $comment
     *
     * @return static
     */
    public function comment(string $comment): static;

    /**
     * Add the column after another column.
     *
     * @param string $name
     *
     * @return static
     */
    public function after(string $name): static;

    /**
     * Add the column as the first column in the table.
     *
     * @return static
     */
    public function first(): static;
}
