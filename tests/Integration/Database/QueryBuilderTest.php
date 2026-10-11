<?php
declare(strict_types=1);

namespace Tests\Integration\Database;

use Engine\Database\Connection;
use Engine\Database\Query\Delete;
use Engine\Database\Query\Expressions;
use Engine\Database\Query\Expressions\RawExpression;
use Engine\Database\Query\Insert;
use Engine\Database\Query\Select;
use Engine\Database\Query\Update;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('integration'), Group('database'), Group('query-builder')]
class QueryBuilderTest extends TestCase
{
    private static Connection $connection;

    public static function setUpBeforeClass(): void
    {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) (getenv('DB_HOST') ?: '127.0.0.1'),
                (int) (getenv('DB_PORT') ?: 3306),
                (string) (getenv('DB_DATABASE') ?: 'engine_test'),
            ),
            (string) (getenv('DB_USERNAME') ?: 'engine'),
            (string) (getenv('DB_PASSWORD') ?: 'secret'),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );

        self::$connection = new Connection('test', $pdo);

        self::$connection->execute('
            CREATE TABLE qb_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                status VARCHAR(50) NOT NULL DEFAULT \'active\',
                age INT NULL
            ) ENGINE=InnoDB
        ');

        self::$connection->execute('
            CREATE TABLE qb_posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                FULLTEXT INDEX ft_title_body (title, body)
            ) ENGINE=InnoDB
        ');

        self::$connection->execute('
            CREATE TABLE qb_counters (
                name VARCHAR(255) NOT NULL PRIMARY KEY,
                count INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
        ');
    }

    public static function tearDownAfterClass(): void
    {
        self::$connection->execute('DROP TABLE IF EXISTS qb_users');
        self::$connection->execute('DROP TABLE IF EXISTS qb_posts');
        self::$connection->execute('DROP TABLE IF EXISTS qb_counters');
    }

    protected function setUp(): void
    {
        self::$connection->execute('DELETE FROM qb_users');
        self::$connection->execute('DELETE FROM qb_posts');
        self::$connection->execute('DELETE FROM qb_counters');
        self::$connection->execute('ALTER TABLE qb_users AUTO_INCREMENT = 1');
        self::$connection->execute('ALTER TABLE qb_posts AUTO_INCREMENT = 1');
    }

    // -------------------------------------------------------------------------
    // Insert
    // -------------------------------------------------------------------------

    /**
     * - Insert a single row and verify it exists with correct data.
     */
    #[Test]
    public function insertSingleRow(): void
    {
        $insert = Insert::into('qb_users')
            ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30])
        ;

        $writeResult = self::$connection->execute($insert);

        $this->assertTrue($writeResult->wasSuccessful());

        $row = self::$connection->query(
            Select::from('qb_users')->where('email', '=', 'alice@example.com'),
        )->first();

        $this->assertNotNull($row);
        $this->assertSame('Alice', $row->string('name'));
        $this->assertSame(30, $row->int('age'));
    }

    /**
     * - Insert multiple rows via chained values() and verify count is 3.
     */
    #[Test]
    public function insertBulk(): void
    {
        $insert = Insert::into('qb_users')
            ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25])
            ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30])
            ->values(['name' => 'Carol', 'email' => 'carol@example.com', 'age' => 35])
        ;

        self::$connection->execute($insert);

        $rows = self::$connection->query(Select::from('qb_users'))->all();

        $this->assertCount(3, $rows);
    }

    /**
     * - INSERT IGNORE with duplicate email leaves count at 1 and preserves original name.
     */
    #[Test]
    public function insertIgnore(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25]),
        );

        self::$connection->execute(
            Insert::into('qb_users')
                ->ignore()
                ->values(['name' => 'Alice Duplicate', 'email' => 'alice@example.com', 'age' => 26]),
        );

        $rows = self::$connection->query(Select::from('qb_users'))->all();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]->string('name'));
    }

    /**
     * - REPLACE INTO with same primary key updates the row with the new count.
     */
    #[Test]
    public function replaceInto(): void
    {
        self::$connection->execute(
            Insert::into('qb_counters')
                ->values(['name' => 'hits', 'count' => 5]),
        );

        self::$connection->execute(
            Insert::into('qb_counters')
                ->replace()
                ->values(['name' => 'hits', 'count' => 99]),
        );

        $row = self::$connection->query(
            Select::from('qb_counters')->where('name', '=', 'hits'),
        )->first();

        $this->assertNotNull($row);
        $this->assertSame(99, $row->int('count'));
    }

    /**
     * - Upsert increments an existing counter value via RawExpression.
     */
    #[Test]
    public function upsert(): void
    {
        self::$connection->execute(
            Insert::into('qb_counters')
                ->values(['name' => 'hits', 'count' => 1]),
        );

        self::$connection->execute(
            Insert::into('qb_counters')
                ->values(['name' => 'hits', 'count' => 1])
                ->upsert(['count' => RawExpression::make('count + 1', [])]),
        );

        $row = self::$connection->query(
            Select::from('qb_counters')->where('name', '=', 'hits'),
        )->first();

        $this->assertNotNull($row);
        $this->assertSame(2, $row->int('count'));
    }

    // -------------------------------------------------------------------------
    // Select
    // -------------------------------------------------------------------------

    /**
     * - Select all rows returns the correct number of results.
     */
    #[Test]
    public function selectAll(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30]),
        );

        $rows = self::$connection->query(Select::from('qb_users'))->all();

        $this->assertCount(2, $rows);
    }

    /**
     * - Select with where clause returns only matching rows.
     */
    #[Test]
    public function selectWithWhere(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active', 'age' => 25])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'inactive', 'age' => 30]),
        );

        $rows = self::$connection->query(
            Select::from('qb_users')->where('status', '=', 'active'),
        )->all();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]->string('name'));
    }

    /**
     * - Group by with having returns only groups matching the condition.
     */
    #[Test]
    public function selectWithGroupByAndHaving(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active', 'age' => 25])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'active', 'age' => 30])
                ->values(['name' => 'Carol', 'email' => 'carol@example.com', 'status' => 'inactive', 'age' => 35]),
        );

        $rows = self::$connection->query(
            Select::from('qb_users')
                ->columns('status', Expressions::count('*'))
                ->groupBy('status')
                ->havingRaw('COUNT(*) > 1'),
        )->all();

        $this->assertCount(1, $rows);
        $this->assertSame('active', $rows[0]->string('status'));
    }

    /**
     * - Order by with limit returns the first N rows in the correct order.
     */
    #[Test]
    public function selectWithOrderByAndLimit(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 28])
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30]),
        );

        $rows = self::$connection->query(
            Select::from('qb_users')->orderBy('name', 'asc')->limit(2),
        )->all();

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]->string('name'));
        $this->assertSame('Bob', $rows[1]->string('name'));
    }

    /**
     * - Aggregate functions return correct COUNT, MIN, MAX, AVG, SUM values.
     */
    #[Test]
    public function selectAggregates(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 35])
                ->values(['name' => 'Carol', 'email' => 'carol@example.com', 'age' => 30]),
        );

        $row = self::$connection->query(
            Select::from('qb_users')->columns(
                Expressions::count('*'),
                Expressions::min('age'),
                Expressions::max('age'),
                Expressions::avg('age'),
                Expressions::sum('age'),
            ),
        )->first();

        $this->assertNotNull($row);
        $this->assertSame(3, $row->int('COUNT(*)'));
        $this->assertSame(25, $row->int('MIN(age)'));
        $this->assertSame(35, $row->int('MAX(age)'));
        $this->assertSame(90, $row->int('SUM(age)'));

        $avg = $row->float('AVG(age)');
        $this->assertGreaterThanOrEqual(30.0, $avg);
    }

    /**
     * - Full-text search returns rows matching the search term.
     */
    #[Test]
    public function selectFullTextSearch(): void
    {
        self::$connection->execute(
            Insert::into('qb_posts')
                ->values(['user_id' => 1, 'title' => 'Introduction to PHP programming', 'body' => 'PHP is a popular language for web development.'])
                ->values(['user_id' => 1, 'title' => 'Advanced PHP techniques', 'body' => 'PHP programming concepts for experienced developers.'])
                ->values(['user_id' => 2, 'title' => 'Python basics', 'body' => 'Python is a versatile programming language.']),
        );

        $rows = self::$connection->query(
            Select::from('qb_posts')->whereFullText(['title', 'body'], 'PHP programming'),
        )->all();

        $this->assertGreaterThanOrEqual(1, count($rows));

        $titles = array_map(fn ($row) => $row->string('title'), $rows);
        $this->assertContains('Introduction to PHP programming', $titles);
    }

    /**
     * - An inner join returns rows matching the join condition.
     */
    #[Test]
    public function selectWithInnerJoin(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')->values(['name' => 'Alice', 'email' => 'alice@example.com']),
        );

        $userId = self::$connection->query(
            Select::from('qb_users')->where('email', '=', 'alice@example.com'),
        )->first()->int('id');

        self::$connection->execute(
            Insert::into('qb_posts')
                ->values(['user_id' => $userId, 'title' => 'Hello World', 'body' => 'First post content here']),
        );

        $result = self::$connection->query(
            Select::from('qb_users')
                ->columns('qb_users.name', 'qb_posts.title')
                ->join('qb_posts', 'qb_users.id', '=', 'qb_posts.user_id'),
        );

        $this->assertCount(1, $result->all());
        $this->assertSame('Alice', $result->first()->get('name'));
        $this->assertSame('Hello World', $result->first()->get('title'));
    }

    /**
     * - A left join returns all rows from the left table.
     */
    #[Test]
    public function selectWithLeftJoin(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'alice@example.com'])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com']),
        );

        $userId = self::$connection->query(
            Select::from('qb_users')->where('email', '=', 'alice@example.com'),
        )->first()->int('id');

        self::$connection->execute(
            Insert::into('qb_posts')
                ->values(['user_id' => $userId, 'title' => 'Hello', 'body' => 'Post body content']),
        );

        $result = self::$connection->query(
            Select::from('qb_users')
                ->columns('qb_users.name', 'qb_posts.title')
                ->leftJoin('qb_posts', 'qb_users.id', '=', 'qb_posts.user_id'),
        );

        $this->assertCount(2, $result->all());
    }

    /**
     * - A distinct select returns unique rows only.
     */
    #[Test]
    public function selectDistinct(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'a@example.com', 'status' => 'active'])
                ->values(['name' => 'Bob', 'email' => 'b@example.com', 'status' => 'active'])
                ->values(['name' => 'Charlie', 'email' => 'c@example.com', 'status' => 'inactive']),
        );

        $result = self::$connection->query(
            Select::from('qb_users')->distinct()->columns('status'),
        );

        $this->assertCount(2, $result->all());
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    /**
     * - Basic update changes the target row and reports 1 affected row.
     */
    #[Test]
    public function updateBasic(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active', 'age' => 25]),
        );

        $writeResult = self::$connection->execute(
            Update::table('qb_users')
                ->set(['status' => 'inactive'])
                ->where('email', '=', 'alice@example.com'),
        );

        $this->assertSame(1, $writeResult->affectedRows());

        $row = self::$connection->query(
            Select::from('qb_users')->where('email', '=', 'alice@example.com'),
        )->first();

        $this->assertNotNull($row);
        $this->assertSame('inactive', $row->string('status'));
    }

    /**
     * - Update with a RawExpression increments a value in place.
     */
    #[Test]
    public function updateWithExpression(): void
    {
        self::$connection->execute(
            Insert::into('qb_counters')
                ->values(['name' => 'hits', 'count' => 10]),
        );

        self::$connection->execute(
            Update::table('qb_counters')
                ->set(['count' => RawExpression::make('count + 5', [])])
                ->where('name', '=', 'hits'),
        );

        $row = self::$connection->query(
            Select::from('qb_counters')->where('name', '=', 'hits'),
        )->first();

        $this->assertNotNull($row);
        $this->assertSame(15, $row->int('count'));
    }

    /**
     * - Update with order by and limit affects only the first alphabetical row.
     */
    #[Test]
    public function updateWithOrderByAndLimit(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Charlie', 'email' => 'charlie@example.com', 'status' => 'active', 'age' => 28])
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active', 'age' => 25])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'active', 'age' => 30]),
        );

        self::$connection->execute(
            Update::table('qb_users')
                ->set(['status' => 'inactive'])
                ->orderBy('name', 'asc')
                ->limit(1),
        );

        $inactive = self::$connection->query(
            Select::from('qb_users')->where('status', '=', 'inactive'),
        )->all();

        $this->assertCount(1, $inactive);
        $this->assertSame('Alice', $inactive[0]->string('name'));
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    /**
     * - Basic delete removes the target row and reports 1 affected row.
     */
    #[Test]
    public function deleteBasic(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30]),
        );

        $writeResult = self::$connection->execute(
            Delete::from('qb_users')->where('email', '=', 'alice@example.com'),
        );

        $this->assertSame(1, $writeResult->affectedRows());

        $rows = self::$connection->query(Select::from('qb_users'))->all();

        $this->assertCount(1, $rows);
        $this->assertSame('Bob', $rows[0]->string('name'));
    }

    /**
     * - Delete with order by and limit removes only the first alphabetical row.
     */
    #[Test]
    public function deleteWithOrderByAndLimit(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 28])
                ->values(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25])
                ->values(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30]),
        );

        self::$connection->execute(
            Delete::from('qb_users')->orderBy('name', 'asc')->limit(1),
        );

        $rows = self::$connection->query(Select::from('qb_users'))->all();

        $this->assertCount(2, $rows);

        $names = array_map(fn ($row) => $row->string('name'), $rows);
        $this->assertFalse(in_array('Alice', $names, true));
    }

    // -------------------------------------------------------------------------
    // Raw
    // -------------------------------------------------------------------------

    /**
     * - Raw SQL query executes and returns the expected result.
     */
    #[Test]
    public function rawQuery(): void
    {
        $row = self::$connection->query('SELECT 1 + 1 AS result')->first();

        $this->assertNotNull($row);
        $this->assertSame(2, $row->int('result'));
    }

    // -------------------------------------------------------------------------
    // Stream
    // -------------------------------------------------------------------------

    /**
     * - stream() yields rows one at a time via cursor.
     */
    #[Test]
    public function streamYieldsRowsViaCursor(): void
    {
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'a@example.com'])
                ->values(['name' => 'Bob', 'email' => 'b@example.com']),
        );

        $cursor = self::$connection->stream(
            Select::from('qb_users')->orderBy('name', 'asc'),
        );

        $names = [];

        $cursor->each(function ($row) use (&$names) {
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
        self::$connection->execute(
            Insert::into('qb_users')
                ->values(['name' => 'Alice', 'email' => 'a@example.com'])
                ->values(['name' => 'Bob', 'email' => 'b@example.com']),
        );

        $cursor = self::$connection->stream(
            Select::from('qb_users')->orderBy('name', 'asc'),
        );

        $names = [];

        foreach ($cursor->rows() as $row) {
            $names[] = $row->get('name');
        }

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    /**
     * - A committed transaction persists data.
     */
    #[Test]
    public function transactionCommitPersistsData(): void
    {
        self::$connection->transaction(function (Connection $conn) {
            $conn->execute(
                Insert::into('qb_users')->values(['name' => 'Alice', 'email' => 'alice@example.com']),
            );
        });

        $result = self::$connection->query(Select::from('qb_users'));

        $this->assertCount(1, $result->all());
    }

    /**
     * - A rolled back transaction does not persist data.
     */
    #[Test]
    public function transactionRollbackDiscardsData(): void
    {
        try {
            self::$connection->transaction(function (Connection $conn) {
                $conn->execute(
                    Insert::into('qb_users')->values(['name' => 'Alice', 'email' => 'alice@example.com']),
                );
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $result = self::$connection->query(Select::from('qb_users'));

        $this->assertCount(0, $result->all());
    }
}
