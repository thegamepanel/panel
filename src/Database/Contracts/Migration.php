<?php
declare(strict_types=1);

namespace Engine\Database\Contracts;

use Engine\Database\Migrations\Migrator;

/**
 * Migration Contract
 * ------------------
 *
 * Represents a database migration that can be executed.
 */
interface Migration
{
    /**
     * Run the migration.
     *
     * @param Migrator $migrator
     */
    public function up(Migrator $migrator): void;
}
