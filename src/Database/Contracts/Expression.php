<?php
declare(strict_types=1);

namespace Engine\Database\Contracts;

/**
 * Expression Contract
 * -------------------
 *
 * Represents an SQL expression that can be transformed into a query string
 * and supports parameter bindings.
 */
interface Expression
{
    /**
     * Get the SQL representation of the expression.
     *
     * @return string
     */
    public function toSql(): string;

    /**
     * Get the bindings for the expression.
     *
     * @return array<int|string, mixed>
     */
    public function getBindings(): array;
}
