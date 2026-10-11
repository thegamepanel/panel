<?php
declare(strict_types=1);

namespace Tests\Unit\Config;

use Engine\Config\ConfigCatalogue;
use Engine\Config\ConfigRegistry;
use Engine\Config\Exceptions\ConfigLifecycleException;
use Engine\Config\Exceptions\ConfigNotRegisteredException;
use Engine\Config\Exceptions\InvalidConfigException;
use Engine\Config\Modules\ModulesEnabled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Unit\Config\Fixtures\MultiKeyConfigObject;
use Tests\Unit\Config\Fixtures\TestConfigObject;
use Tests\Unit\Config\Fixtures\ThrowingConfigObject;

#[Group('unit'), Group('config'), Group('registry')]
class ConfigRegistryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // sealCore()
    // -------------------------------------------------------------------------

    /**
     * - sealCore() hydrates each entry in the core mapping from the tree.
     */
    #[Test]
    public function sealCoreHydratesCoreEntries(): void
    {
        $registry = new ConfigRegistry(
            tree: [
                '__enabled_modules' => ['admin', 'billing'],
            ],
            coreMapping: [
                '__enabled_modules' => ModulesEnabled::class,
            ],
        );

        $registry->sealCore();

        $modules = $registry->for(ModulesEnabled::class);
        $this->assertInstanceOf(ModulesEnabled::class, $modules);
        $this->assertSame(['admin', 'billing'], $modules->modules);
    }

    /**
     * - sealCore() called twice throws.
     */
    #[Test]
    public function sealCoreTwiceThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();

        $this->expectException(ConfigLifecycleException::class);
        $registry->sealCore();
    }

    /**
     * - sealCore() throws when the tree's section is not an array.
     */
    #[Test]
    public function sealCoreThrowsWhenSectionIsNotArray(): void
    {
        $registry = new ConfigRegistry(
            tree: [
                '__enabled_modules' => 'not-an-array',
            ],
            coreMapping: [
                '__enabled_modules' => ModulesEnabled::class,
            ],
        );

        try {
            $registry->sealCore();
            $this->fail('Expected InvalidConfigException.');
        } catch (InvalidConfigException $e) {
            $this->assertStringContainsString('__enabled_modules', $e->getMessage());
            $this->assertStringContainsString('Expected an array', $e->getMessage());
        }
    }

    /**
     * - sealCore() with a missing tree section hydrates the core class from an empty array.
     */
    #[Test]
    public function sealCoreHydratesFromEmptyWhenSectionMissing(): void
    {
        $registry = new ConfigRegistry(
            tree: [],
            coreMapping: [
                '__enabled_modules' => ModulesEnabled::class,
            ],
        );
        $registry->sealCore();

        $modules = $registry->for(ModulesEnabled::class);
        $this->assertSame([], $modules->modules);
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    /**
     * - register() before sealCore() throws.
     */
    #[Test]
    public function registerBeforeCoreSealThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);

        $this->expectException(ConfigLifecycleException::class);
        $this->expectExceptionMessage('Module configs cannot be registered before sealCore()');

        $registry->register('foo', 'bar', TestConfigObject::class);
    }

    /**
     * - register() with the reserved 'engine' module name throws.
     */
    #[Test]
    public function registerWithCoreModuleNameThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();

        $this->expectException(ConfigLifecycleException::class);
        $this->expectExceptionMessage('reserved for core configs');

        $registry->register('engine', 'whatever', TestConfigObject::class);
    }

    /**
     * - register() rejects a module name containing a dot.
     */
    #[Test]
    public function registerWithDottedModuleNameThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();

        $this->expectException(ConfigLifecycleException::class);
        $this->expectExceptionMessage('must not contain dots');

        $registry->register('admin.v2', 'main', TestConfigObject::class);
    }

    /**
     * - register() rejects a config name containing a dot.
     */
    #[Test]
    public function registerWithDottedConfigNameThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();

        $this->expectException(ConfigLifecycleException::class);
        $this->expectExceptionMessage('must not contain dots');

        $registry->register('admin', 'main.v2', TestConfigObject::class);
    }

    /**
     * - register() succeeds in the middle phase and stores the registration.
     *
     * (We verify storage indirectly via seal() in a later task; here we just
     * verify the call does not throw.)
     */
    #[Test]
    public function registerInMiddlePhaseDoesNotThrow(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();

        $registry->register('billing', 'main', TestConfigObject::class);

        $this->expectNotToPerformAssertions();
    }

    // -------------------------------------------------------------------------
    // for()
    // -------------------------------------------------------------------------

    /**
     * - for() before sealCore() throws.
     */
    #[Test]
    public function forBeforeSealCoreThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);

        $this->expectException(ConfigLifecycleException::class);
        $this->expectExceptionMessage('before sealCore()');

        $registry->for(ModulesEnabled::class);
    }

    /**
     * - for() for an unregistered class throws ConfigNotRegisteredException.
     */
    #[Test]
    public function forUnregisteredClassThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();

        $this->expectException(ConfigNotRegisteredException::class);

        $registry->for(TestConfigObject::class);
    }

    /**
     * - for() returns the same instance on repeated calls.
     */
    #[Test]
    public function forReturnsSameInstanceOnRepeatedCalls(): void
    {
        $registry = new ConfigRegistry(
            tree: ['__enabled_modules' => ['a']],
            coreMapping: ['__enabled_modules' => ModulesEnabled::class],
        );
        $registry->sealCore();

        $first  = $registry->for(ModulesEnabled::class);
        $second = $registry->for(ModulesEnabled::class);

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // seal()
    // -------------------------------------------------------------------------

    /**
     * - seal() returns a ConfigCatalogue.
     */
    #[Test]
    public function sealReturnsCatalogue(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();

        $catalogue = $registry->seal();

        $this->assertInstanceOf(ConfigCatalogue::class, $catalogue);
    }

    /**
     * - seal() called twice throws.
     */
    #[Test]
    public function sealTwiceThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();
        $registry->seal();

        $this->expectException(ConfigLifecycleException::class);
        $registry->seal();
    }

    /**
     * - seal() before sealCore() throws.
     */
    #[Test]
    public function sealBeforeCoreSealThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);

        $this->expectException(ConfigLifecycleException::class);
        $this->expectExceptionMessage('seal() cannot be called before sealCore()');

        $registry->seal();
    }

    /**
     * - register() after seal() throws.
     */
    #[Test]
    public function registerAfterSealThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();
        $registry->seal();

        $this->expectException(ConfigLifecycleException::class);
        $registry->register('foo', 'bar', TestConfigObject::class);
    }

    /**
     * - for() after seal() throws.
     */
    #[Test]
    public function forAfterSealThrows(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();
        $registry->seal();

        $this->expectException(ConfigLifecycleException::class);
        $registry->for(ModulesEnabled::class);
    }

    /**
     * - seal() hydrates registered module configs from the tree at modules.<module>.<name>.
     */
    #[Test]
    public function sealHydratesModuleRegistrations(): void
    {
        $registry = new ConfigRegistry(
            tree: [
                'modules' => [
                    'billing' => [
                        'main' => ['value' => 'hello'],
                    ],
                ],
            ],
            coreMapping: [],
        );
        $registry->sealCore();
        $registry->register('billing', 'main', TestConfigObject::class);

        $catalogue = $registry->seal();

        $config = $catalogue->for(TestConfigObject::class);
        $this->assertInstanceOf(TestConfigObject::class, $config);
        $this->assertSame('hello', $config->value);
    }

    /**
     * - seal() catalogue places core entries under the 'engine' module label.
     */
    #[Test]
    public function sealPlacesCoreUnderEngineModule(): void
    {
        $registry = new ConfigRegistry(
            tree: ['__enabled_modules' => ['admin']],
            coreMapping: ['__enabled_modules' => ModulesEnabled::class],
        );
        $registry->sealCore();
        $catalogue = $registry->seal();

        $this->assertTrue($catalogue->has('engine', '__enabled_modules'));
    }

    /**
     * - seal() wraps fromArray failures in InvalidConfigException with file/section/previous.
     */
    #[Test]
    public function sealWrapsModuleHydrationFailures(): void
    {
        $registry = new ConfigRegistry(
            tree: [
                'modules' => [
                    'broken' => [
                        'main' => ['some' => 'thing'],
                    ],
                ],
            ],
            coreMapping: [],
        );
        $registry->sealCore();
        $registry->register('broken', 'main', ThrowingConfigObject::class);

        try {
            $registry->seal();
            $this->fail('Expected InvalidConfigException.');
        } catch (InvalidConfigException $e) {
            $this->assertStringContainsString('modules-enabled/broken.toml', $e->getMessage());
            $this->assertStringContainsString('main', $e->getMessage());
            $this->assertNotNull($e->getPrevious());

            $previous = $e->getPrevious();
            $this->assertInstanceOf(RuntimeException::class, $previous);
            $this->assertSame('boom', $previous->getMessage());
        }
    }

    /**
     * - seal() preserves all keys in a multi-key section subtree (not just the first).
     *
     * Kills the ArrayOneItem mutant in pluck() that would slice the section to its first key.
     */
    #[Test]
    public function sealPreservesAllKeysInMultiKeySection(): void
    {
        $registry = new ConfigRegistry(
            tree: [
                'modules' => [
                    'billing' => [
                        'main' => [
                            'value' => 'first',
                            'extra' => 'second',
                        ],
                    ],
                ],
            ],
            coreMapping: [],
        );
        $registry->sealCore();
        $registry->register('billing', 'main', MultiKeyConfigObject::class);

        $catalogue = $registry->seal();

        $config = $catalogue->for(MultiKeyConfigObject::class);
        $this->assertSame('first', $config->value);
        $this->assertSame('second', $config->extra);
    }

    /**
     * - seal() hydrates a registered module with an empty array when the section is missing.
     */
    #[Test]
    public function sealHydratesFromEmptyWhenModuleSectionMissing(): void
    {
        $registry = new ConfigRegistry(tree: [], coreMapping: []);
        $registry->sealCore();
        $registry->register('billing', 'main', TestConfigObject::class);

        $catalogue = $registry->seal();

        $config = $catalogue->for(TestConfigObject::class);
        $this->assertNull($config->value);
    }

    // -------------------------------------------------------------------------
    // pluck() error paths
    // -------------------------------------------------------------------------

    /**
     * - seal() with a tree where an intermediate path segment is not an array throws.
     */
    #[Test]
    public function sealThrowsWhenIntermediatePathIsNotArray(): void
    {
        // modules.broken is a string, not an array — pluck() can't descend into it.
        $registry = new ConfigRegistry(
            tree: [
                'modules' => [
                    'broken' => 'not-an-array',
                ],
            ],
            coreMapping: [],
        );
        $registry->sealCore();
        $registry->register('broken', 'main', TestConfigObject::class);

        try {
            $registry->seal();
            $this->fail('Expected InvalidConfigException.');
        } catch (InvalidConfigException $e) {
            $this->assertStringContainsString('modules-enabled/broken.toml', $e->getMessage());
            $this->assertStringContainsString('modules.broken.main', $e->getMessage());
            $this->assertStringContainsString('intermediate path segment', $e->getMessage());
        }
    }

    /**
     * - seal() with a tree where the section's final value is not an array throws.
     */
    #[Test]
    public function sealThrowsWhenFinalValueIsNotArray(): void
    {
        // modules.foo.main is a string, not an array — pluck() reaches the
        // section but discovers a non-array at the leaf.
        $registry = new ConfigRegistry(
            tree: [
                'modules' => [
                    'foo' => [
                        'main' => 'not-an-array',
                    ],
                ],
            ],
            coreMapping: [],
        );
        $registry->sealCore();
        $registry->register('foo', 'main', TestConfigObject::class);

        try {
            $registry->seal();
            $this->fail('Expected InvalidConfigException.');
        } catch (InvalidConfigException $e) {
            $this->assertStringContainsString('modules-enabled/foo.toml', $e->getMessage());
            $this->assertStringContainsString('modules.foo.main', $e->getMessage());
            $this->assertStringContainsString('section path', $e->getMessage());
        }
    }
}
