<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use Engine\Database\Connection;
use Engine\Database\Exceptions\DatabaseException;
use Engine\Database\Exceptions\QueryException;
use Engine\Database\Query\Row;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[Group('unit'), Group('database'), Group('connection')]
class ConnectionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // query()
    // -------------------------------------------------------------------------

    /**
     * - query() returns a Result whose first Row reflects the inserted data.
     */
    #[Test]
    public function queryReturnsResultWithRows(): void
    {
        $conn = $this->connection();
        $conn->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);
        $result = $conn->query('SELECT * FROM test');
        $this->assertSame('Alice', $result->first()->get('name'));
    }

    // -------------------------------------------------------------------------
    // execute()
    // -------------------------------------------------------------------------

    /**
     * - execute() returns a WriteResult reporting one affected row after an INSERT.
     */
    #[Test]
    public function executeReturnsWriteResultWithAffectedRows(): void
    {
        $conn   = $this->connection();
        $result = $conn->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);
        $this->assertSame(1, $result->affectedRows());
    }

    /**
     * - execute() returns a lastInsertId after an INSERT.
     */
    #[Test]
    public function executeReturnsLastInsertId(): void
    {
        $conn   = $this->connection();
        $result = $conn->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);

        $this->assertNotNull($result->lastInsertId());
        $this->assertSame('1', $result->lastInsertId());
    }

    // -------------------------------------------------------------------------
    // stream()
    // -------------------------------------------------------------------------

    /**
     * - stream() returns a Cursor that yields every row via each().
     */
    #[Test]
    public function streamReturnsCursorThatYieldsRows(): void
    {
        $conn = $this->connection();
        $conn->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);
        $conn->execute('INSERT INTO test (name) VALUES (?)', ['Bob']);
        $cursor = $conn->stream('SELECT * FROM test ORDER BY name');
        $names  = [];
        $cursor->each(function (Row $row) use (&$names) {
            $names[] = $row->get('name');
        });
        $this->assertSame(['Alice', 'Bob'], $names);
    }

    /**
     * - stream() returns a Cursor whose rows() generator yields Row objects.
     */
    #[Test]
    public function streamCursorRowsGeneratorYieldsRows(): void
    {
        $conn = $this->connection();
        $conn->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);
        $conn->execute('INSERT INTO test (name) VALUES (?)', ['Bob']);

        $cursor = $conn->stream('SELECT * FROM test ORDER BY name');
        $names  = [];

        foreach ($cursor->rows() as $row) {
            $names[] = $row->get('name');
        }

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    // -------------------------------------------------------------------------
    // transaction() - commit
    // -------------------------------------------------------------------------

    /**
     * - transaction() commits when the callback completes without throwing.
     */
    #[Test]
    public function transactionCommitsOnSuccess(): void
    {
        $conn = $this->connection();
        $conn->transaction(function (Connection $c) {
            $c->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);
        });
        $this->assertFalse($conn->isInTransaction());
        $this->assertSame('Alice', $conn->query('SELECT * FROM test')->first()->get('name'));
    }

    // -------------------------------------------------------------------------
    // transaction() - rollback
    // -------------------------------------------------------------------------

    /**
     * - transaction() rolls back and re-throws when the callback throws.
     */
    #[Test]
    public function transactionRollsBackOnException(): void
    {
        $conn = $this->connection();
        try {
            $conn->transaction(function (Connection $c) {
                $c->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);
                throw new RuntimeException('fail');
            });
        } catch (RuntimeException) {
        }
        $this->assertCount(0, $conn->query('SELECT * FROM test')->all());
    }

    // -------------------------------------------------------------------------
    // transaction() - return value
    // -------------------------------------------------------------------------

    /**
     * - transaction() passes the callback's return value back to the caller.
     */
    #[Test]
    public function transactionReturnsCallbackResult(): void
    {
        $this->assertSame(42, $this->connection()->transaction(fn () => 42));
    }

    // -------------------------------------------------------------------------
    // beginTransaction() / commit()
    // -------------------------------------------------------------------------

    /**
     * - beginTransaction() opens a transaction and commit() persists the changes.
     */
    #[Test]
    public function manualBeginAndCommit(): void
    {
        $conn = $this->connection();
        $conn->beginTransaction();
        $this->assertTrue($conn->isInTransaction());
        $conn->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);
        $conn->commit();
        $this->assertFalse($conn->isInTransaction());
        $this->assertSame('Alice', $conn->query('SELECT * FROM test')->first()->get('name'));
    }

    // -------------------------------------------------------------------------
    // beginTransaction() / rollback()
    // -------------------------------------------------------------------------

    /**
     * - beginTransaction() opens a transaction and rollback() discards the changes.
     */
    #[Test]
    public function manualBeginAndRollback(): void
    {
        $conn = $this->connection();
        $conn->beginTransaction();
        $conn->execute('INSERT INTO test (name) VALUES (?)', ['Alice']);
        $conn->rollback();
        $this->assertFalse($conn->isInTransaction());
        $this->assertCount(0, $conn->query('SELECT * FROM test')->all());
    }
    // -------------------------------------------------------------------------
    // Error paths
    // -------------------------------------------------------------------------

    /**
     * - query() throws QueryException for invalid SQL.
     */
    #[Test]
    public function queryThrowsQueryExceptionForInvalidSql(): void
    {
        $this->expectException(QueryException::class);

        $this->connection()->query('SELECT * FROM nonexistent_table');
    }

    /**
     * - QueryException exposes the SQL and bindings from the failed query.
     */
    #[Test]
    public function queryExceptionExposesSqlAndBindings(): void
    {
        try {
            $this->connection()->execute('INSERT INTO nonexistent_table (col) VALUES (?)', ['val']);
            $this->fail('Expected QueryException');
        } catch (QueryException $e) {
            $this->assertSame('INSERT INTO nonexistent_table (col) VALUES (?)', $e->getSql());
            $this->assertSame(['val'], $e->getBindings());
            $this->assertNotEmpty($e->getMessage());
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
            $this->assertSame($e->getPrevious()->getMessage(), $e->getMessage());
        }
    }

    /**
     * - execute() throws QueryException for invalid SQL.
     */
    #[Test]
    public function executeThrowsQueryExceptionForInvalidSql(): void
    {
        $this->expectException(QueryException::class);

        $this->connection()->execute('INSERT INTO nonexistent_table (col) VALUES (?)', ['val']);
    }

    /**
     * - beginTransaction() throws DatabaseException when already in a transaction.
     */
    #[Test]
    public function beginTransactionThrowsDatabaseExceptionWhenAlreadyInTransaction(): void
    {
        $this->expectException(DatabaseException::class);

        $conn = $this->connection();
        $conn->beginTransaction();
        $conn->beginTransaction();
    }

    // -------------------------------------------------------------------------
    // processBindings()
    // -------------------------------------------------------------------------

    /**
     * - processBindings() converts boolean values to integers.
     */
    #[Test]
    public function processBindingsConvertsBoolsToInts(): void
    {
        $conn   = $this->connection();
        $method = new ReflectionMethod($conn, 'processBindings');

        $this->assertSame(
            [1, 0, 'hello', 42, null],
            $method->invoke($conn, [true, false, 'hello', 42, null]),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * - Build an in-memory SQLite Connection with a pre-created test table.
     */
    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        return new Connection('test', $pdo);
    }
}
