<?php
declare(strict_types=1);

namespace Tests\Unit\Database\Config;

use Engine\Database\Config\ConnectionConfig;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit'), Group('database'), Group('connection-config')]
class ConnectionConfigTest extends TestCase
{
    // -------------------------------------------------------------------------
    // fromArray()
    // -------------------------------------------------------------------------

    /**
     * - fromArray() builds a ConnectionConfig from the host/port shape.
     */
    #[Test]
    public function fromArrayBuildsConfigFromHostPortShape(): void
    {
        $options = [PDO::ATTR_TIMEOUT => 5];

        $config = ConnectionConfig::fromArray([
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'database' => 'engine_test',
            'username' => 'engine',
            'password' => 'secret',
            'options'  => $options,
        ]);

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(3306, $config->port);
        $this->assertNull($config->socket);
        $this->assertSame('engine_test', $config->database);
        $this->assertSame('engine', $config->username);
        $this->assertSame('secret', $config->password);
        $this->assertSame($options, $config->options);
        $this->assertSame('mysql', $config->driver);
    }

    /**
     * - fromArray() builds a ConnectionConfig from the socket shape.
     */
    #[Test]
    public function fromArrayBuildsConfigFromSocketShape(): void
    {
        $options = [PDO::ATTR_TIMEOUT => 5];

        $config = ConnectionConfig::fromArray([
            'socket'   => '/var/run/mysql.sock',
            'database' => 'engine_test',
            'username' => 'engine',
            'password' => 'secret',
            'options'  => $options,
        ]);

        $this->assertNull($config->host);
        $this->assertNull($config->port);
        $this->assertSame('/var/run/mysql.sock', $config->socket);
        $this->assertSame('engine_test', $config->database);
        $this->assertSame('engine', $config->username);
        $this->assertSame('secret', $config->password);
        $this->assertSame($options, $config->options);
        $this->assertSame('mysql', $config->driver);
    }

    // -------------------------------------------------------------------------
    // fromArray() validation
    // -------------------------------------------------------------------------

    /**
     * - fromArray() throws when the 'database' key is missing.
     */
    #[Test]
    public function fromArrayThrowsWhenDatabaseKeyMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'port'     => 3306,
            'username' => 'u',
            'password' => 'p',
        ]);
    }

    /**
     * - fromArray() throws when 'database' is an empty string.
     */
    #[Test]
    public function fromArrayThrowsWhenDatabaseIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => '',
            'username' => 'u',
            'password' => 'p',
        ]);
    }

    /**
     * - fromArray() throws when the 'username' key is missing.
     */
    #[Test]
    public function fromArrayThrowsWhenUsernameKeyMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'db',
            'password' => 'p',
        ]);
    }

    /**
     * - fromArray() throws when 'username' is an empty string.
     */
    #[Test]
    public function fromArrayThrowsWhenUsernameIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'db',
            'username' => '',
            'password' => 'p',
        ]);
    }

    /**
     * - fromArray() throws when the 'password' key is missing.
     */
    #[Test]
    public function fromArrayThrowsWhenPasswordKeyMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'db',
            'username' => 'u',
        ]);
    }

    /**
     * - fromArray() throws when 'password' is an empty string.
     */
    #[Test]
    public function fromArrayThrowsWhenPasswordIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'db',
            'username' => 'u',
            'password' => '',
        ]);
    }

    /**
     * - fromArray() throws when 'options' is set but not an array.
     */
    #[Test]
    public function fromArrayThrowsWhenOptionsIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'db',
            'username' => 'u',
            'password' => 'p',
            'options'  => 'not-an-array',
        ]);
    }

    /**
     * - fromArray() throws when 'socket' is set but empty.
     */
    #[Test]
    public function fromArrayThrowsWhenSocketIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'socket'   => '',
            'database' => 'db',
            'username' => 'u',
            'password' => 'p',
        ]);
    }

    /**
     * - fromArray() throws when the 'host' key is missing in the host/port branch.
     */
    #[Test]
    public function fromArrayThrowsWhenHostKeyMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'port'     => 3306,
            'database' => 'db',
            'username' => 'u',
            'password' => 'p',
        ]);
    }

    /**
     * - fromArray() throws when 'host' is an empty string.
     */
    #[Test]
    public function fromArrayThrowsWhenHostIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => '',
            'port'     => 3306,
            'database' => 'db',
            'username' => 'u',
            'password' => 'p',
        ]);
    }

    /**
     * - fromArray() throws when the 'port' key is missing in the host/port branch.
     */
    #[Test]
    public function fromArrayThrowsWhenPortKeyMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'database' => 'db',
            'username' => 'u',
            'password' => 'p',
        ]);
    }

    /**
     * - fromArray() throws when 'port' is not an integer.
     */
    #[Test]
    public function fromArrayThrowsWhenPortIsNotInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromArray([
            'host'     => 'localhost',
            'port'     => '3306',  // string, not int
            'database' => 'db',
            'username' => 'u',
            'password' => 'p',
        ]);
    }

    // -------------------------------------------------------------------------
    // make() validation
    // -------------------------------------------------------------------------

    /**
     * - make() rejects empty database.
     */
    #[Test]
    public function makeThrowsWhenDatabaseIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::make('localhost', 3306, null, '', 'u', 'p');
    }

    /**
     * - make() rejects empty username.
     */
    #[Test]
    public function makeThrowsWhenUsernameIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::make('localhost', 3306, null, 'db', '', 'p');
    }

    /**
     * - make() rejects empty password.
     */
    #[Test]
    public function makeThrowsWhenPasswordIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::make('localhost', 3306, null, 'db', 'u', '');
    }

    /**
     * - make() rejects empty socket.
     */
    #[Test]
    public function makeThrowsWhenSocketIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::make(null, null, '', 'db', 'u', 'p');
    }

    /**
     * - make() rejects null host when socket is also null.
     */
    #[Test]
    public function makeThrowsWhenNoHostNoSocket(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::make(null, 3306, null, 'db', 'u', 'p');
    }

    /**
     * - make() rejects null port when socket is also null.
     */
    #[Test]
    public function makeThrowsWhenNoPortNoSocket(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::make('localhost', null, null, 'db', 'u', 'p');
    }
}
