<?php
declare(strict_types=1);

namespace Engine\Database\Contracts;

/**
 * Query Contract
 * --------------
 *
 * Represents an SQL query, a specific type of {@see Expression}. Used to
 * typehint when the expression should be treated as a full query.
 */
interface Query extends Expression
{
}
