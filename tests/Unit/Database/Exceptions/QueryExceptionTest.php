<?php
declare(strict_types=1);

namespace Tests\Unit\Database\Exceptions;

use Engine\Database\Exceptions\QueryException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('unit'), Group('database'), Group('query-exception')]
class QueryExceptionTest extends TestCase
{
    /**
     * - Constructor uses previous exception message when no explicit message given.
     */
    #[Test]
    public function constructorUsesPreviousExceptionMessage(): void
    {
        $previous  = new RuntimeException('connection lost');
        $exception = new QueryException('SELECT 1', [], previous: $previous);

        $this->assertSame('connection lost', $exception->getMessage());
    }

    /**
     * - Constructor uses explicit message over previous exception message.
     */
    #[Test]
    public function constructorUsesExplicitMessageOverPrevious(): void
    {
        $previous  = new RuntimeException('connection lost');
        $exception = new QueryException('SELECT 1', [], 'custom message', $previous);

        $this->assertSame('custom message', $exception->getMessage());
    }

    /**
     * - Constructor uses fallback message when no message and no previous given.
     */
    #[Test]
    public function constructorUsesFallbackMessageWhenNoPrevious(): void
    {
        $exception = new QueryException('SELECT 1', []);

        $this->assertSame('Unable to execute the query.', $exception->getMessage());
    }

    /**
     * - getSql() returns the SQL string.
     */
    #[Test]
    public function getSqlReturnsSqlString(): void
    {
        $exception = new QueryException('SELECT * FROM users WHERE id = ?', [1]);

        $this->assertSame('SELECT * FROM users WHERE id = ?', $exception->getSql());
    }

    /**
     * - getBindings() returns the bindings array.
     */
    #[Test]
    public function getBindingsReturnsBindingsArray(): void
    {
        $exception = new QueryException('SELECT * FROM users WHERE id = ?', [1]);

        $this->assertSame([1], $exception->getBindings());
    }
}
