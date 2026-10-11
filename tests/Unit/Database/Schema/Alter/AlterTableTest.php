<?php
declare(strict_types=1);

namespace Tests\Unit\Database\Schema\Alter;

use Engine\Database\Schema\Alter;
use Engine\Database\Schema\Alter\AlterTable;
use Engine\Database\Schema\Column;
use Engine\Database\Schema\Drop;
use Engine\Database\Schema\Index;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit'), Group('database'), Group('alter-table')]
class AlterTableTest extends TestCase
{
    #[Test]
    public function renameTable(): void
    {
        $alter = new AlterTable('users');
        $alter->rename('accounts');

        $this->assertSame(
            'ALTER TABLE `users` RENAME TO `accounts`',
            $alter->toSql(),
        );
    }

    #[Test]
    public function addColumn(): void
    {
        $alter = new AlterTable('users');
        $alter->add(Column::varchar('email', 255)->notNull());

        $this->assertSame(
            'ALTER TABLE `users` ADD COLUMN `email` VARCHAR(255) NOT NULL',
            $alter->toSql(),
        );
    }

    #[Test]
    public function addIndex(): void
    {
        $alter = new AlterTable('users');
        $alter->add(Index::unique('idx_email', 'email'));

        $this->assertSame(
            'ALTER TABLE `users` ADD UNIQUE INDEX `idx_email` (`email`)',
            $alter->toSql(),
        );
    }

    #[Test]
    public function modifyColumn(): void
    {
        $alter = new AlterTable('users');
        $alter->modify(Column::varchar('name', 512)->notNull());

        $this->assertSame(
            'ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(512) NOT NULL',
            $alter->toSql(),
        );
    }

    #[Test]
    public function dropColumn(): void
    {
        $alter = new AlterTable('users');
        $alter->drop(Drop::column('email'));

        $this->assertSame(
            'ALTER TABLE `users` DROP COLUMN `email`',
            $alter->toSql(),
        );
    }

    #[Test]
    public function dropIndex(): void
    {
        $alter = new AlterTable('users');
        $alter->drop(Drop::index('idx_email'));

        $this->assertSame(
            'ALTER TABLE `users` DROP INDEX `idx_email`',
            $alter->toSql(),
        );
    }

    #[Test]
    public function dropPrimaryKey(): void
    {
        $alter = new AlterTable('users');
        $alter->drop(Drop::primaryKey('pk'));

        $this->assertSame(
            'ALTER TABLE `users` DROP PRIMARY KEY',
            $alter->toSql(),
        );
    }

    #[Test]
    public function dropForeignKey(): void
    {
        $alter = new AlterTable('users');
        $alter->drop(Drop::foreignKey('fk_users_org'));

        $this->assertSame(
            'ALTER TABLE `users` DROP FOREIGN KEY `fk_users_org`',
            $alter->toSql(),
        );
    }

    #[Test]
    public function renameColumn(): void
    {
        $alter = new AlterTable('users');
        $alter->move('name', 'full_name');

        $this->assertSame(
            'ALTER TABLE `users` RENAME COLUMN `name` TO `full_name`',
            $alter->toSql(),
        );
    }

    #[Test]
    public function combinedOperationsRespectOrdering(): void
    {
        $alter = new AlterTable('users');
        $alter
            ->rename('accounts')
            ->drop(
                Drop::primaryKey('pk'),
                Drop::index('idx_email'),
                Drop::foreignKey('fk_org'),
                Drop::column('legacy'),
            )
            ->modify(Column::varchar('name', 512)->notNull())
            ->move('email', 'email_address')
            ->add(
                Column::varchar('phone', 20)->nullable(),
                Index::index('idx_phone', 'phone'),
            )
        ;

        $this->assertSame(
            'ALTER TABLE `users`'
            . ' RENAME TO `accounts`,'
            . ' DROP PRIMARY KEY,'
            . ' DROP INDEX `idx_email`,'
            . ' DROP FOREIGN KEY `fk_org`,'
            . ' DROP COLUMN `legacy`,'
            . ' MODIFY COLUMN `name` VARCHAR(512) NOT NULL,'
            . ' RENAME COLUMN `email` TO `email_address`,'
            . ' ADD COLUMN `phone` VARCHAR(20) NULL,'
            . ' ADD INDEX `idx_phone` (`phone`)',
            $alter->toSql(),
        );
    }

    #[Test]
    public function modifyCanBeCalledMultipleTimes(): void
    {
        $alter = new AlterTable('users');
        $alter->modify(Column::varchar('name', 100));
        $alter->modify(Column::varchar('email', 255));

        $this->assertSame(
            'ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(100), MODIFY COLUMN `email` VARCHAR(255)',
            $alter->toSql(),
        );
    }

    #[Test]
    public function getBindingsReturnsEmptyArray(): void
    {
        $alter = new AlterTable('users');
        $alter->add(Column::int('id'));

        $this->assertSame([], $alter->getBindings());
    }

    #[Test]
    public function dropThrowsForInvalidDropType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $alter = new AlterTable('users');
        $alter->drop(Drop::table('other'));
    }

    #[Test]
    public function factoryMethodReturnsAlterTableInstance(): void
    {
        $alter = Alter::table('users');

        $this->assertInstanceOf(AlterTable::class, $alter);
    }
}
