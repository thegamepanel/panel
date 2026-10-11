<?php
declare(strict_types=1);

namespace Tests\Unit\Container;

use Engine\Container\Exceptions\InvalidResolutionException;
use Engine\Container\Resolution;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Container\Fixtures\ClassWithMethods;
use Tests\Unit\Container\Fixtures\TestQualifier;
use Tests\Unit\Container\Fixtures\TestResolvable;

#[Group('unit'), Group('container'), Group('resolution')]
class ResolutionTest extends TestCase
{
    /**
     * - A fresh resolution defaults to eager, non-liminal, unnamed, unqualified,
     *   uses no custom resolver, and carries no arguments.
     */
    #[Test]
    public function createsBareInstanceSuccessfully(): void
    {
        $instance = Resolution::for(ClassWithMethods::class);

        $this->assertSame(ClassWithMethods::class, $instance->class);
        $this->assertEmpty($instance->arguments);
        $this->assertNull($instance->name);
        $this->assertNull($instance->qualifier);
        $this->assertNull($instance->resolvable);
        $this->assertFalse($instance->lazily, 'The default resolution mode should be eager.');
        $this->assertFalse($instance->liminal, 'The default liminal mode should be false');
    }

    /**
     * - All state predicates return false on a newly created resolution, so nothing
     *   is unintentionally active before the caller opts in.
     */
    #[Test]
    public function allPredicatesReturnFalseOnFreshInstance(): void
    {
        $instance = Resolution::for(ClassWithMethods::class);

        $this->assertFalse($instance->shouldResolveLazily());
        $this->assertFalse($instance->isNamed());
        $this->assertFalse($instance->isLiminal());
        $this->assertFalse($instance->isQualified());
        $this->assertFalse($instance->usesCustomResolver());
    }

    /**
     * - `with()` modifies the resolution in place and returns the same instance,
     *   allowing fluent method chaining.
     */
    #[Test]
    public function withArgumentsMutatesAndReturnsTheSameInstance(): void
    {
        $instance = Resolution::for(ClassWithMethods::class);
        $result   = $instance->with(['key' => 'value']);

        $this->assertSame($instance, $result);
        $this->assertSame(['key' => 'value'], $instance->arguments);
    }

    /**
     * - Calling `with()` multiple times accumulates arguments across calls rather
     *   than discarding previously provided values.
     */
    #[Test]
    public function withArgumentsMergesSubsequentCalls(): void
    {
        $instance = Resolution::for(ClassWithMethods::class);
        $instance->with(['key1' => 'value1']);
        $instance->with(['key2' => 'value2']);

        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $instance->arguments);
    }

    /**
     * - `named()` modifies the resolution in place, stores the name, activates the
     *   named lookup predicate, and returns the same instance for fluent chaining.
     */
    #[Test]
    public function namedMutatesAndReturnsTheSameInstance(): void
    {
        $instance = Resolution::for(ClassWithMethods::class);
        $result   = $instance->named('my-binding');

        $this->assertSame($instance, $result);
        $this->assertSame('my-binding', $instance->name);
        $this->assertTrue($instance->isNamed());
    }

    /**
     * - `qualifiedBy()` modifies the resolution in place, stores the qualifier,
     *   activates the qualified lookup predicate, and returns the same instance.
     */
    #[Test]
    public function qualifiedByMutatesAndReturnsTheSameInstance(): void
    {
        $qualifier = new TestQualifier();

        $instance = Resolution::for(ClassWithMethods::class);
        $result   = $instance->qualifiedBy($qualifier);

        $this->assertSame($instance, $result);
        $this->assertSame($qualifier, $instance->qualifier);
        $this->assertTrue($instance->isQualified());
    }

    /**
     * - `named()` on a resolution that already has a qualifier throws, since a
     *   resolution cannot be both named and qualified.
     */
    #[Test]
    public function namedAfterQualifiedByThrowsInvalidResolutionException(): void
    {
        $instance = Resolution::for(ClassWithMethods::class)->qualifiedBy(new TestQualifier());

        $this->expectException(InvalidResolutionException::class);
        $this->expectExceptionMessage(sprintf(
            'Class "%s" cannot have both a name "%s", and qualifier "%s".',
            ClassWithMethods::class,
            'primary',
            TestQualifier::class,
        ));

        $instance->named('primary');
    }

    /**
     * - `qualifiedBy()` on a resolution that already has a name throws, for the same
     *   reason.
     */
    #[Test]
    public function qualifiedByAfterNamedThrowsInvalidResolutionException(): void
    {
        $instance = Resolution::for(ClassWithMethods::class)->named('primary');

        $this->expectException(InvalidResolutionException::class);
        $this->expectExceptionMessage(sprintf(
            'Class "%s" cannot have both a name "%s", and qualifier "%s".',
            ClassWithMethods::class,
            'primary',
            TestQualifier::class,
        ));

        $instance->qualifiedBy(new TestQualifier());
    }

    /**
     * - `resolveWith()` modifies the resolution in place, stores the resolvable,
     *   activates the custom resolver predicate, and returns the same instance.
     */
    #[Test]
    public function resolveWithMutatesAndReturnsTheSameInstance(): void
    {
        $resolvable = new TestResolvable();

        $instance = Resolution::for(ClassWithMethods::class);
        $result   = $instance->resolveWith($resolvable);

        $this->assertSame($instance, $result);
        $this->assertSame($resolvable, $instance->resolvable);
        $this->assertTrue($instance->usesCustomResolver());
    }

    /**
     * - `lazily()` modifies the resolution in place, activates the lazy resolution
     *   predicate, and returns the same instance for fluent chaining.
     */
    #[Test]
    public function lazilyMutatesAndReturnsTheSameInstance(): void
    {
        $instance = Resolution::for(ClassWithMethods::class);
        $result   = $instance->lazily();

        $this->assertSame($instance, $result);
        $this->assertTrue($instance->lazily);
        $this->assertTrue($instance->shouldResolveLazily());
    }

    /**
     * - `liminal()` modifies the resolution in place, activates the liminal scope
     *   predicate, and returns the same instance for fluent chaining.
     */
    #[Test]
    public function liminalMutatesAndReturnsTheSameInstance(): void
    {
        $instance = Resolution::for(ClassWithMethods::class);
        $result   = $instance->liminal();

        $this->assertSame($instance, $result);
        $this->assertTrue($instance->liminal);
        $this->assertTrue($instance->isLiminal());
    }
}
