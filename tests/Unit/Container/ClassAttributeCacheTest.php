<?php
declare(strict_types=1);

namespace Tests\Unit\Container;

use Engine\Container\Attributes\Lazy;
use Engine\Container\Attributes\Liminal;
use Engine\Container\Attributes\NoResolution;
use Engine\Container\ClassAttributeCache;
use Engine\Container\Exceptions\InvalidAttributeException;
use Engine\Container\Exceptions\InvalidClassException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Unit\Container\Fixtures\ClassWithNoResolution;
use Tests\Unit\Container\Fixtures\ClassWithThrowingAttribute;
use Tests\Unit\Container\Fixtures\LazyClass;
use Tests\Unit\Container\Fixtures\LiminalClass;
use Tests\Unit\Container\Fixtures\ThrowingAttribute;

#[Group('unit'), Group('container'), Group('class-attribute-cache')]
class ClassAttributeCacheTest extends TestCase
{
    /**
     * - A class carrying a marker reads as having it.
     *
     * @param class-string                            $class
     * @param class-string<NoResolution|Liminal|Lazy> $marker
     */
    #[Test, DataProvider('markerDataProvider')]
    public function hasReturnsTrueForAClassCarryingTheMarker(string $class, string $marker): void
    {
        $this->assertTrue(new ClassAttributeCache()->has($class, $marker));
    }

    /**
     * - A class carrying one marker reads as not having the other two.
     *
     * @param class-string                            $class
     * @param class-string<NoResolution|Liminal|Lazy> $marker
     */
    #[Test, DataProvider('markerDataProvider')]
    public function hasReturnsFalseForMarkersTheClassDoesNotCarry(string $class, string $marker): void
    {
        $cache = new ClassAttributeCache();

        foreach (self::markerDataProvider() as [, $other]) {
            if ($other === $marker) {
                continue;
            }

            $this->assertFalse($cache->has($class, $other));
        }
    }

    /**
     * Each marker with a fixture class that carries it.
     *
     * @return array<string, array{class-string, class-string<NoResolution|Liminal|Lazy>}>
     */
    public static function markerDataProvider(): array
    {
        return [
            'NoResolution' => [ClassWithNoResolution::class, NoResolution::class],
            'Lazy'         => [LazyClass::class, Lazy::class],
            'Liminal'      => [LiminalClass::class, Liminal::class],
        ];
    }

    /**
     * - A class carrying only an untracked attribute reads as having no marker, and the
     *   untracked attribute is never instantiated, since its constructor throws.
     */
    #[Test]
    public function hasReturnsFalseWithoutInstantiatingOtherAttributes(): void
    {
        $cache = new ClassAttributeCache();

        foreach (self::markerDataProvider() as [, $marker]) {
            $this->assertFalse($cache->has(ClassWithThrowingAttribute::class, $marker));
        }
    }

    /**
     * - After the first lookup for a class, later lookups answer from the stored flags
     *   rather than scanning the class again. Replacing the stored flags proves the
     *   second answer comes from the cache.
     */
    #[Test]
    public function hasReadsStoredFlagsAfterTheFirstLookup(): void
    {
        $cache = new ClassAttributeCache();

        $this->assertTrue($cache->has(LazyClass::class, Lazy::class));

        new ReflectionProperty(ClassAttributeCache::class, 'attributes')->setValue($cache, [
            LazyClass::class => [
                NoResolution::class => false,
                Liminal::class      => false,
                Lazy::class         => false,
            ],
        ]);

        $this->assertFalse($cache->has(LazyClass::class, Lazy::class));
    }

    /**
     * - Flags are stored per class, so one class's markers never answer for another.
     */
    #[Test]
    public function hasKeepsEachClassSeparate(): void
    {
        $cache = new ClassAttributeCache();

        $this->assertTrue($cache->has(LazyClass::class, Lazy::class));
        $this->assertFalse($cache->has(LiminalClass::class, Lazy::class));
        $this->assertTrue($cache->has(LiminalClass::class, Liminal::class));
        $this->assertFalse($cache->has(LazyClass::class, Liminal::class));
    }

    /**
     * - A class that does not exist throws when it is first looked up, and nothing is
     *   stored for it, so a later lookup fails the same way rather than answering.
     */
    #[Test]
    public function hasWithANonexistentClassThrowsAndStoresNothing(): void
    {
        $cache = new ClassAttributeCache();

        try {
            /** @phpstan-ignore argument.type */
            $cache->has('Missing\Thing', Lazy::class);
            $this->fail('Expected an InvalidClassException');
        } catch (InvalidClassException $e) {
            $this->assertSame('The provided class Missing\Thing is not a valid class.', $e->getMessage());
        }

        $this->assertSame([], new ReflectionProperty(ClassAttributeCache::class, 'attributes')->getValue($cache));
    }

    /**
     * - Asking about an attribute that is not a tracked marker throws.
     */
    #[Test]
    public function hasWithAnUntrackedAttributeThrowsInvalidAttributeException(): void
    {
        $this->expectException(InvalidAttributeException::class);
        $this->expectExceptionMessage(sprintf(
            'The attribute "%s" is not a class-level marker the container tracks',
            ThrowingAttribute::class,
        ));

        /** @phpstan-ignore argument.type */
        new ClassAttributeCache()->has(LazyClass::class, ThrowingAttribute::class);
    }
}
