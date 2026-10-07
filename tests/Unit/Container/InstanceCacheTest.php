<?php
declare(strict_types=1);

namespace Tests\Unit\Container;

use Closure;
use Engine\Container\Exceptions\InvalidResolutionException;
use Engine\Container\InstanceCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Container\Fixtures\AnotherTestQualifier;
use Tests\Unit\Container\Fixtures\ClassWithMethods;
use Tests\Unit\Container\Fixtures\ClassWithProperty;
use Tests\Unit\Container\Fixtures\TestQualifier;

#[Group('unit'), Group('container'), Group('instance-cache')]
class InstanceCacheTest extends TestCase
{
    /**
     * - An instance stored under a class, a class and name, or a class and qualifier
     *   class is returned by a read under the same key, whichever way the cache holds it.
     *
     * @param Closure(): InstanceCache<bool>                 $cache
     * @param array{name?: string, qualifier?: class-string} $dimension
     */
    #[Test, DataProvider('cacheAndDimensionDataProvider')]
    public function putThenGetReturnsTheInstance(Closure $cache, array $dimension): void
    {
        $cache    = $cache();
        $instance = new ClassWithMethods();

        $cache->put(ClassWithMethods::class, $instance, ...$dimension);

        $this->assertSame($instance, $cache->get(ClassWithMethods::class, ...$dimension));
    }

    /**
     * - A read under a key nothing was stored under returns null.
     *
     * @param Closure(): InstanceCache<bool>                 $cache
     * @param array{name?: string, qualifier?: class-string} $dimension
     */
    #[Test, DataProvider('cacheAndDimensionDataProvider')]
    public function getReturnsNullWhenNothingIsStored(Closure $cache, array $dimension): void
    {
        $this->assertNull($cache()->get(ClassWithMethods::class, ...$dimension));
    }

    /**
     * - An instance stored under one dimension is not found by a read under either of
     *   the other two for the same class.
     *
     * @param Closure(): InstanceCache<bool>                 $cache
     * @param array{name?: string, qualifier?: class-string} $dimension
     */
    #[Test, DataProvider('cacheAndDimensionDataProvider')]
    public function getDoesNotCrossDimensions(Closure $cache, array $dimension): void
    {
        $cache    = $cache();
        $instance = new ClassWithMethods();

        $cache->put(ClassWithMethods::class, $instance, ...$dimension);

        foreach (self::dimensionDataProvider() as [$other]) {
            if ($other === $dimension) {
                continue;
            }

            $this->assertNull($cache->get(ClassWithMethods::class, ...$other));
        }
    }

    /**
     * - A plain, a named and a qualified entry for one class hold three separate
     *   instances, as do two names and two qualifier classes under one class.
     *
     * @param Closure(): InstanceCache<bool> $cache
     */
    #[Test, DataProvider('cacheDataProvider')]
    public function entriesUnderOneClassAreDistinct(Closure $cache): void
    {
        $cache     = $cache();
        $plain     = new ClassWithMethods();
        $primary   = new ClassWithMethods();
        $secondary = new ClassWithMethods();
        $test      = new ClassWithMethods();
        $another   = new ClassWithMethods();

        $cache->put(ClassWithMethods::class, $plain);
        $cache->put(ClassWithMethods::class, $primary, name: 'primary');
        $cache->put(ClassWithMethods::class, $secondary, name: 'secondary');
        $cache->put(ClassWithMethods::class, $test, qualifier: TestQualifier::class);
        $cache->put(ClassWithMethods::class, $another, qualifier: AnotherTestQualifier::class);

        $this->assertSame($plain, $cache->get(ClassWithMethods::class));
        $this->assertSame($primary, $cache->get(ClassWithMethods::class, name: 'primary'));
        $this->assertSame($secondary, $cache->get(ClassWithMethods::class, name: 'secondary'));
        $this->assertSame($test, $cache->get(ClassWithMethods::class, qualifier: TestQualifier::class));
        $this->assertSame($another, $cache->get(ClassWithMethods::class, qualifier: AnotherTestQualifier::class));
    }

    /**
     * - Entries for different classes do not collide, under any dimension.
     *
     * @param Closure(): InstanceCache<bool>                 $cache
     * @param array{name?: string, qualifier?: class-string} $dimension
     */
    #[Test, DataProvider('cacheAndDimensionDataProvider')]
    public function entriesForDifferentClassesAreDistinct(Closure $cache, array $dimension): void
    {
        $cache    = $cache();
        $methods  = new ClassWithMethods();
        $property = new ClassWithProperty();

        $cache->put(ClassWithMethods::class, $methods, ...$dimension);
        $cache->put(ClassWithProperty::class, $property, ...$dimension);

        $this->assertSame($methods, $cache->get(ClassWithMethods::class, ...$dimension));
        $this->assertSame($property, $cache->get(ClassWithProperty::class, ...$dimension));
    }

    /**
     * - A second put under the same key replaces the first instance.
     *
     * @param Closure(): InstanceCache<bool>                 $cache
     * @param array{name?: string, qualifier?: class-string} $dimension
     */
    #[Test, DataProvider('cacheAndDimensionDataProvider')]
    public function putReplacesAnExistingEntry(Closure $cache, array $dimension): void
    {
        $cache  = $cache();
        $first  = new ClassWithMethods();
        $second = new ClassWithMethods();

        $cache->put(ClassWithMethods::class, $first, ...$dimension);
        $cache->put(ClassWithMethods::class, $second, ...$dimension);

        $this->assertSame($second, $cache->get(ClassWithMethods::class, ...$dimension));
    }

    /**
     * @return array<string, array{Closure(): InstanceCache<bool>, array{name?: string, qualifier?: class-string}}>
     */
    public static function cacheAndDimensionDataProvider(): array
    {
        $cases = [];

        foreach (self::cacheDataProvider() as $cacheName => [$cache]) {
            foreach (self::dimensionDataProvider() as $dimensionName => [$dimension]) {
                $cases[$cacheName . ', ' . $dimensionName] = [$cache, $dimension];
            }
        }

        return $cases;
    }

    /**
     * - `put()` returns the cache, so calls can be chained.
     *
     * @param Closure(): InstanceCache<bool> $cache
     */
    #[Test, DataProvider('cacheDataProvider')]
    public function putReturnsTheCache(Closure $cache): void
    {
        $cache = $cache();

        $this->assertSame($cache, $cache->put(ClassWithMethods::class, new ClassWithMethods()));
    }

    /**
     * - A put given both a name and a qualifier is rejected, since a resolution cannot
     *   have both, and the exception names the class, name and qualifier.
     *
     * @param Closure(): InstanceCache<bool> $cache
     */
    #[Test, DataProvider('cacheDataProvider')]
    public function putWithBothNameAndQualifierThrows(Closure $cache): void
    {
        $this->expectException(InvalidResolutionException::class);
        $this->expectExceptionMessage(sprintf(
            'Class "%s" cannot have both a name "%s", and qualifier "%s".',
            ClassWithMethods::class,
            'primary',
            TestQualifier::class,
        ));

        $cache()->put(ClassWithMethods::class, new ClassWithMethods(), name: 'primary', qualifier: TestQualifier::class);
    }

    /**
     * @return array<string, array{Closure(): InstanceCache<bool>}>
     */
    public static function cacheDataProvider(): array
    {
        return [
            'strong' => [static fn (): InstanceCache => InstanceCache::strong()],
            'weak'   => [static fn (): InstanceCache => InstanceCache::weak()],
        ];
    }

    /**
     * - A strong cache keeps its instance alive once the caller has dropped every other
     *   reference to it.
     *
     * @param array{name?: string, qualifier?: class-string} $dimension
     */
    #[Test, DataProvider('dimensionDataProvider')]
    public function strongCacheHoldsTheInstance(array $dimension): void
    {
        $cache    = InstanceCache::strong();
        $instance = new ClassWithMethods();
        $weak     = \WeakReference::create($instance);

        $cache->put(ClassWithMethods::class, $instance, ...$dimension);
        unset($instance);
        gc_collect_cycles();

        $this->assertNotNull($weak->get());
        $this->assertSame($weak->get(), $cache->get(ClassWithMethods::class, ...$dimension));
    }

    /**
     * - A weak cache returns null once the instance has been collected, rather than the
     *   weak reference or an error.
     *
     * @param array{name?: string, qualifier?: class-string} $dimension
     */
    #[Test, DataProvider('dimensionDataProvider')]
    public function weakCacheReturnsNullOnceTheInstanceIsCollected(array $dimension): void
    {
        $cache    = InstanceCache::weak();
        $instance = new ClassWithMethods();

        $cache->put(ClassWithMethods::class, $instance, ...$dimension);
        unset($instance);
        gc_collect_cycles();

        $this->assertNull($cache->get(ClassWithMethods::class, ...$dimension));
    }

    /**
     * - A collected entry in a weak cache is replaced by the next put under the same key,
     *   and the read then returns the new instance.
     *
     * @param array{name?: string, qualifier?: class-string} $dimension
     */
    #[Test, DataProvider('dimensionDataProvider')]
    public function weakCacheReplacesACollectedEntry(array $dimension): void
    {
        $cache = InstanceCache::weak();
        $first = new ClassWithMethods();

        $cache->put(ClassWithMethods::class, $first, ...$dimension);
        unset($first);
        gc_collect_cycles();

        $second = new ClassWithMethods();
        $cache->put(ClassWithMethods::class, $second, ...$dimension);

        $this->assertSame($second, $cache->get(ClassWithMethods::class, ...$dimension));
    }

    /**
     * Each dimension as the named arguments that select it.
     *
     * @return array<string, array{array{name?: string, qualifier?: class-string}}>
     */
    public static function dimensionDataProvider(): array
    {
        return [
            'class'     => [[]],
            'named'     => [['name' => 'primary']],
            'qualified' => [['qualifier' => TestQualifier::class]],
        ];
    }
}
