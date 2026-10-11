<?php
declare(strict_types=1);

namespace Tests\Unit\Config;

use Engine\Config\ConfigCatalogue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Config\Fixtures\AnotherTestConfigObject;
use Tests\Unit\Config\Fixtures\TestConfigObject;

#[Group('unit'), Group('config'), Group('catalogue')]
class ConfigCatalogueTest extends TestCase
{
    private ConfigCatalogue $catalogue;

    private TestConfigObject $objectA;

    private TestConfigObject $objectB;

    protected function setUp(): void
    {
        $this->objectA = new TestConfigObject();
        $this->objectB = new TestConfigObject();

        $this->catalogue = new ConfigCatalogue([
            'moduleA' => [
                'configA' => $this->objectA,
            ],
            'moduleB' => [
                'configB' => $this->objectB,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // has()
    // -------------------------------------------------------------------------

    /**
     * - has() returns true when the module and config key both exist.
     */
    #[Test]
    public function hasReturnsTrueForExistingModuleAndConfig(): void
    {
        $this->assertTrue($this->catalogue->has('moduleA', 'configA'));
    }

    /**
     * - has() returns false when the module does not exist.
     */
    #[Test]
    public function hasReturnsFalseForMissingModule(): void
    {
        $this->assertFalse($this->catalogue->has('missing', 'configA'));
    }

    /**
     * - has() returns false when the module exists but the config key does not.
     */
    #[Test]
    public function hasReturnsFalseForMissingConfigInExistingModule(): void
    {
        $this->assertFalse($this->catalogue->has('moduleA', 'missing'));
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    /**
     * - get() returns the ConfigObject for a known module and config key.
     */
    #[Test]
    public function getReturnsConfigObjectForExistingEntry(): void
    {
        $this->assertSame($this->objectA, $this->catalogue->get('moduleA', 'configA'));
    }

    /**
     * - get() returns null when the module does not exist.
     */
    #[Test]
    public function getReturnsNullForMissingModule(): void
    {
        $this->assertNull($this->catalogue->get('missing', 'configA'));
    }

    /**
     * - get() returns null when the config key does not exist within a known module.
     */
    #[Test]
    public function getReturnsNullForMissingConfig(): void
    {
        $this->assertNull($this->catalogue->get('moduleA', 'missing'));
    }

    // -------------------------------------------------------------------------
    // for()
    // -------------------------------------------------------------------------

    /**
     * - for() returns the ConfigObject when the class is registered.
     */
    #[Test]
    public function forReturnsConfigObjectForKnownClass(): void
    {
        $object    = new TestConfigObject();
        $catalogue = new ConfigCatalogue(['mod' => ['cfg' => $object]]);

        $this->assertSame($object, $catalogue->for(TestConfigObject::class));
    }

    /**
     * - for() returns null when the class has no mapping.
     */
    #[Test]
    public function forReturnsNullForUnknownClass(): void
    {
        $this->assertNull($this->catalogue->for(stdClass::class));
    }

    /**
     * - for() resolves the correct object when multiple distinct classes are registered.
     *   A catalogue with two different classes is required to catch an ArrayOneItem
     *   mutant that truncates classMappings to a single entry.
     */
    #[Test]
    public function forResolvesSecondClassWhenMultipleClassesAreRegistered(): void
    {
        $first  = new TestConfigObject();
        $second = new AnotherTestConfigObject();

        $catalogue = new ConfigCatalogue([
            'modA' => ['cfgA' => $first],
            'modB' => ['cfgB' => $second],
        ]);

        $this->assertSame($second, $catalogue->for(AnotherTestConfigObject::class));
    }

    // -------------------------------------------------------------------------
    // Empty catalogue
    // -------------------------------------------------------------------------

    /**
     * - An empty catalogue returns false for has() and null for get() and for().
     */
    #[Test]
    public function emptyRegistryReturnsFalseAndNull(): void
    {
        $empty = new ConfigCatalogue([]);

        $this->assertFalse($empty->has('module', 'config'));
        $this->assertNull($empty->get('module', 'config'));
        $this->assertNull($empty->for(TestConfigObject::class));
    }
}
