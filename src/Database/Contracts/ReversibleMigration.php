<?php
declare(strict_types=1);

namespace Engine\Database\Contracts;

use Engine\Database\Migrations\Migrator;

/**
 * Reversible Migration Contract
 * -----------------------------
 *
 * Represents a database migration that can be both executed and rolled back.
 */
interface ReversibleMigration extends Migration
{
    /**
     * Reverse the migration.
     *
     * @param Migrator $migrator
     */
    public function down(Migrator $migrator): void;
}
