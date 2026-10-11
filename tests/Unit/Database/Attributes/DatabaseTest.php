<?php
declare(strict_types=1);

namespace Tests\Unit\Database\Attributes;

use Attribute;
use Engine\Container\Contracts\Resolvable;
use Engine\Database\Attributes\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[Group('unit'), Group('database'), Group('database-attribute')]
class DatabaseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    /**
     * - The Database attribute implements the Resolvable contract.
     */
    #[Test]
    public function implementsResolvableContract(): void
    {
        $this->assertInstanceOf(Resolvable::class, new Database());
    }

    // -------------------------------------------------------------------------
    // Name property
    // -------------------------------------------------------------------------

    /**
     * - The name defaults to null when not specified.
     */
    #[Test]
    public function nameDefaultsToNull(): void
    {
        $database = new Database();

        $this->assertNull($database->name);
    }

    /**
     * - The name is set when provided to the constructor.
     */
    #[Test]
    public function nameIsSetWhenProvided(): void
    {
        $database = new Database('secondary');

        $this->assertSame('secondary', $database->name);
    }

    // -------------------------------------------------------------------------
    // Attribute target
    // -------------------------------------------------------------------------

    /**
     * - The attribute targets parameters only.
     */
    #[Test]
    public function targetsParametersOnly(): void
    {
        $attributes = new ReflectionClass(Database::class)->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(Attribute::TARGET_PARAMETER, $attributes[0]->newInstance()->flags);
    }
}
