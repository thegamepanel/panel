<?php
declare(strict_types=1);

namespace Engine\Database\Contracts;

/**
 * Schema Contract
 * ---------------
 *
 * Represents an SQL schema operation, a specific type of {@see Expression}.
 * Used to typehint when the expression should be treated as a DDL schema
 * operation.
 */
interface Schema extends Expression
{
}
