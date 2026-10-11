<?php
declare(strict_types=1);

namespace Tests\Unit\Database\Exceptions;

use Engine\Database\Exceptions\ConnectionException;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit'), Group('database'), Group('connection-exception')]
class ConnectionExceptionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Fallback message
    // -------------------------------------------------------------------------

    /**
     * - Constructor with no message and no previous uses the fallback string
     *   containing the connection name.
     */
    #[Test]
    public function constructorUsesNameInFallbackMessageWhenNoPreviousOrMessage(): void
    {
        $exception = new ConnectionException('myconn');

        $this->assertSame('Unable to connect to the database "myconn"', $exception->getMessage());
    }

    // -------------------------------------------------------------------------
    // cannotConnect()
    // -------------------------------------------------------------------------

    /**
     * - cannotConnect() uses the previous exception's message when it has one.
     */
    #[Test]
    public function cannotConnectUsesPreviousExceptionMessage(): void
    {
        $previous  = new Exception('connection refused');
        $exception = ConnectionException::cannotConnect('myconn', $previous);

        $this->assertSame('connection refused', $exception->getMessage());
    }

    // -------------------------------------------------------------------------
    // noConfig()
    // -------------------------------------------------------------------------

    /**
     * - noConfig() produces a message containing the connection name.
     */
    #[Test]
    public function noConfigProducesExactMessage(): void
    {
        $exception = ConnectionException::noConfig('myconn');

        $this->assertSame(
            'No configuration found for the database connection "myconn"',
            $exception->getMessage(),
        );
    }
}
