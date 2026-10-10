<?php
declare(strict_types=1);

namespace Tests\Unit\Container;

use Engine\Container\Attributes\Ghost;
use Engine\Container\Attributes\Liminal;
use Engine\Container\Attributes\NoResolution;
use Engine\Container\Bindings\Binding;
use Engine\Container\Bindings\BindingCatalogue;
use Engine\Container\Container;
use Engine\Container\Exceptions\BindingNotFoundException;
use Engine\Container\Exceptions\DependencyResolutionException;
use Engine\Container\Exceptions\InvalidClassException;
use Engine\Container\Exceptions\InvalidInvocationException;
use Engine\Container\Exceptions\NotInstantiableException;
use Engine\Container\Exceptions\UnresolvableClassException;
use Engine\Container\Invocation;
use Engine\Container\Resolution;
use Engine\Container\Resolvers\GenericResolver;
use Engine\Container\Resolvers\GhostResolver;
use Engine\Container\Resolvers\ResolverCatalogue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use Tests\Unit\Container\Fixtures\AbstractInterface;
use Tests\Unit\Container\Fixtures\AnotherTestQualifier;
use Tests\Unit\Container\Fixtures\ClassWithDependency;
use Tests\Unit\Container\Fixtures\ClassWithGhostAbstractDependency;
use Tests\Unit\Container\Fixtures\ClassWithGhostDependency;
use Tests\Unit\Container\Fixtures\ClassWithGhostScalarDependency;
use Tests\Unit\Container\Fixtures\ClassWithLiminalDependency;
use Tests\Unit\Container\Fixtures\ClassWithMethods;
use Tests\Unit\Container\Fixtures\ClassWithMixedParams;
use Tests\Unit\Container\Fixtures\ClassWithMultipleDependencies;
use Tests\Unit\Container\Fixtures\ClassWithNamedAndQualifiedDependency;
use Tests\Unit\Container\Fixtures\ClassWithNamedDependency;
use Tests\Unit\Container\Fixtures\ClassWithNoResolution;
use Tests\Unit\Container\Fixtures\ClassWithPrivateMethod;
use Tests\Unit\Container\Fixtures\ClassWithProperty;
use Tests\Unit\Container\Fixtures\ClassWithRequiredScalarParam;
use Tests\Unit\Container\Fixtures\ClassWithScalarDefault;
use Tests\Unit\Container\Fixtures\ClassWithThrowingAttribute;
use Tests\Unit\Container\Fixtures\ClassWithVariadicParam;
use Tests\Unit\Container\Fixtures\ConcreteClass;
use Tests\Unit\Container\Fixtures\LazyClass;
use Tests\Unit\Container\Fixtures\LazyClassWithNoResolution;
use Tests\Unit\Container\Fixtures\LiminalClass;
use Tests\Unit\Container\Fixtures\LiminalInterface;
use Tests\Unit\Container\Fixtures\NoResolutionInterface;
use Tests\Unit\Container\Fixtures\TaggedQualifier;
use Tests\Unit\Container\Fixtures\TestQualifier;

#[Group('unit'), Group('container')]
class ContainerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Lazy proxy creation
    // -------------------------------------------------------------------------

    /**
     * - A lazy resolution for a class with typed properties returns an uninitialized proxy.
     */
    #[Test]
    public function resolveWithLazilyReturnsUninitializedProxy(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(ClassWithProperty::class)->lazily());

        $this->assertTrue(new ReflectionClass(ClassWithProperty::class)->isUninitializedLazyObject($result));
    }

    /**
     * - A lazy resolution for a class with no typed properties cannot be deferred and
     *   returns an already-initialized instance instead.
     */
    #[Test]
    public function resolveWithLazilyReturnsInitializedObjectWhenClassHasNoUninitializedProperties(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(ClassWithMethods::class)->lazily());

        $this->assertFalse(new ReflectionClass(ClassWithMethods::class)->isUninitializedLazyObject($result));
        $this->assertInstanceOf(ClassWithMethods::class, $result);
    }

    /**
     * - Passing `$skipLazy = true` forces eager resolution even when the resolution is
     *   flagged as lazy, bypassing proxy creation entirely.
     */
    #[Test]
    public function resolveWithSkipLazyBypassesLazyProxy(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(ClassWithProperty::class)->lazily(), true);

        $this->assertFalse(new ReflectionClass(ClassWithProperty::class)->isUninitializedLazyObject($result));
        $this->assertInstanceOf(ClassWithProperty::class, $result);
    }

    /**
     * - Accessing a member on a lazy proxy triggers initialization of the underlying object.
     */
    #[Test]
    public function accessingLazyProxyInitializesTheObject(): void
    {
        $container = $this->buildContainer();
        $proxy     = $container->resolve(Resolution::for(ClassWithProperty::class)->lazily());
        $reflector = new ReflectionClass(ClassWithProperty::class);

        $this->assertTrue($reflector->isUninitializedLazyObject($proxy));

        $proxy->value;

        $this->assertFalse($reflector->isUninitializedLazyObject($proxy));
    }

    /**
     * - A class decorated with `#[Lazy]` is resolved as an uninitialized proxy even
     *   without `Resolution::lazily()` being set by the caller.
     */
    #[Test]
    public function resolveLazyClassReturnsUninitializedProxy(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(LazyClass::class));

        $this->assertTrue(new ReflectionClass(LazyClass::class)->isUninitializedLazyObject($result));
    }

    /**
     * - Passing `$skipLazy = true` bypasses the `#[Lazy]` class attribute and returns
     *   a fully initialized instance directly.
     */
    #[Test]
    public function resolveLazyClassWithSkipLazyReturnsRealInstance(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(LazyClass::class), true);

        $this->assertFalse(new ReflectionClass(LazyClass::class)->isUninitializedLazyObject($result));
        $this->assertInstanceOf(LazyClass::class, $result);
    }

    // -------------------------------------------------------------------------
    // Shared / non-shared binding
    // -------------------------------------------------------------------------

    /**
     * - A shared binding returns the same instance on every subsequent resolution,
     *   acting as a singleton within the container.
     */
    #[Test]
    public function resolveWithSharedBindingReturnsSameInstanceOnSubsequentCalls(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, shared: true),
        );

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertSame($first, $second);
    }

    /**
     * - A non-shared binding creates a fresh instance on each resolution, never
     *   caching the result.
     */
    #[Test]
    public function resolveWithNotSharedBindingReturnsNewInstanceEachTime(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, shared: false),
        );

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertNotSame($first, $second);
    }

    /**
     * - A class with no binding is shared, so a second resolution returns the instance
     *   the first constructed.
     */
    #[Test]
    public function resolveClassWithNoBindingReturnsSameInstanceOnSubsequentCalls(): void
    {
        $container = $this->buildContainerWith();

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // Binding dispatch: factory / instance / concrete
    // -------------------------------------------------------------------------

    /**
     * - When a binding has a factory closure, the container calls it instead of
     *   auto-wiring, and returns whatever the factory produces.
     */
    #[Test]
    public function resolveWithBindingFactoryCallsFactory(): void
    {
        $expected  = new ClassWithMethods();
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, factory: static fn () => $expected, shared: true),
        );

        $result = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertSame($expected, $result);
    }

    /**
     * - When a binding holds a pre-built instance, the container returns it directly
     *   without invoking a factory or auto-wiring.
     */
    #[Test]
    public function resolveWithBindingInstanceReturnsItDirectly(): void
    {
        $expected  = new ClassWithMethods();
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, instance: $expected, shared: true),
        );

        $result = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertSame($expected, $result);
    }

    /**
     * - When a binding maps an abstract to a concrete class name, the container
     *   resolves and instantiates the concrete class.
     */
    #[Test]
    public function resolveWithBindingConcreteInstantiatesConcreteClass(): void
    {
        $container = $this->buildContainerWith(
            new Binding(AbstractInterface::class, concrete: ConcreteClass::class, shared: true),
        );

        $result = $container->resolve(Resolution::for(AbstractInterface::class));

        $this->assertInstanceOf(ConcreteClass::class, $result);
    }

    // -------------------------------------------------------------------------
    // Liminal scope
    // -------------------------------------------------------------------------

    /**
     * - A binding with `liminal = true` stores the resolved instance in the weak
     *   reference store rather than the shared instance store, so a non-liminal
     *   resolution of the same class produces a fresh instance each time.
     */
    #[Test]
    public function resolveWithLiminalBindingDoesNotCacheInSharedInstances(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, liminal: true, shared: true),
        );

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertNotSame($first, $second);
    }

    /**
     * - A liminal resolution stores the instance as a weak reference; once all
     *   strong references are dropped the instance is garbage collected and the
     *   next resolution produces a fresh object.
     */
    #[Test]
    public function resolveWithLiminalResolutionStoresInstanceAsWeakReference(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithProperty::class, shared: true),
        );
        $resolution = Resolution::for(ClassWithProperty::class)->liminal();

        $instance = $container->resolve($resolution);
        $weak     = \WeakReference::create($instance);
        unset($instance);
        gc_collect_cycles();

        $fresh = $container->resolve($resolution);

        $this->assertNull($weak->get());
        $this->assertInstanceOf(ClassWithProperty::class, $fresh);
    }

    /**
     * - When the liminal flag is set by the resolution object and the resolved class
     *   has no `#[Liminal]` attribute, the flag is preserved through the auto-wiring
     *   path so the instance is still stored as a weak reference.
     */
    #[Test]
    public function resolveWithLiminalResolutionPreservesLiminalFlagThroughAutoWiring(): void
    {
        // ClassWithMethods has no constructor and no #[Liminal] attribute;
        // the liminal flag must survive the auto-wiring code path unchanged.
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, shared: true),
        );
        $resolution = Resolution::for(ClassWithMethods::class)->liminal();

        $first  = $container->resolve($resolution);
        $second = $container->resolve($resolution);

        // Both calls resolve to the same WeakReference target while a strong ref exists.
        $this->assertSame($first, $second);
    }

    /**
     * - A class decorated with `#[Liminal]` is stored via a weak reference, so a
     *   second resolution with a shared binding produces a fresh instance when the
     *   non-liminal lookup path is used.
     */
    #[Test]
    public function resolveLiminalClassStoresInstanceWeakly(): void
    {
        $container = $this->buildContainerWith(
            new Binding(LiminalClass::class, shared: true),
        );

        $first  = $container->resolve(Resolution::for(LiminalClass::class));
        $second = $container->resolve(Resolution::for(LiminalClass::class));

        // Stored in liminalInstances (not instances), so non-liminal resolution
        // cannot retrieve it and creates a fresh object each time.
        $this->assertNotSame($first, $second);
    }

    /**
     * - A liminal resolution for a class that is already cached as a non-liminal shared
     *   instance bypasses the shared instance store and creates a fresh object, so that
     *   liminal and non-liminal resolutions of the same class remain independent.
     */
    #[Test]
    public function resolveWithLiminalResolutionDoesNotReturnNonLiminalSharedInstance(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, shared: true),
        );

        $sharedInstance  = $container->resolve(Resolution::for(ClassWithMethods::class));
        $liminalInstance = $container->resolve(Resolution::for(ClassWithMethods::class)->liminal());

        $this->assertNotSame($sharedInstance, $liminalInstance);
    }

    /**
     * - A liminal resolution with a name is cached under the name, so a second resolve
     *   finds it while the instance is alive.
     */
    #[Test]
    public function resolveWithNamedLiminalResolutionReturnsSameInstanceWhileAlive(): void
    {
        $namedBinding = new Binding(ClassWithMethods::class, shared: true);
        $mainBinding  = new Binding(ClassWithMethods::class, namedMap: ['primary' => $namedBinding]);
        $container    = $this->buildContainerWith($mainBinding);

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary')->liminal());
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary')->liminal());

        $this->assertSame($first, $second);
    }

    /**
     * - A liminal resolution with a qualifier is cached under the qualifier class, so a
     *   second resolve finds it while the instance is alive.
     */
    #[Test]
    public function resolveWithQualifiedLiminalResolutionReturnsSameInstanceWhileAlive(): void
    {
        $qualBinding = new Binding(ClassWithMethods::class, shared: true);
        $mainBinding = new Binding(ClassWithMethods::class, qualifiedMap: [TestQualifier::class => $qualBinding]);
        $container   = $this->buildContainerWith($mainBinding);

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TestQualifier())->liminal());
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TestQualifier())->liminal());

        $this->assertSame($first, $second);
    }

    /**
     * - A liminal resolution without a name does not return the instance a named
     *   liminal resolution of the same class cached.
     */
    #[Test]
    public function resolveWithLiminalResolutionDoesNotReturnNamedLiminalInstance(): void
    {
        $namedBinding = new Binding(ClassWithMethods::class, shared: true);
        $mainBinding  = new Binding(ClassWithMethods::class, shared: true, namedMap: ['primary' => $namedBinding]);
        $container    = $this->buildContainerWith($mainBinding);

        $named = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary')->liminal());
        $bare  = $container->resolve(Resolution::for(ClassWithMethods::class)->liminal());

        $this->assertNotSame($named, $bare);
    }

    /**
     * - A non-liminal resolution does not return the instance a liminal resolution of the
     *   same class cached, the reverse of the previous test.
     */
    #[Test]
    public function resolveWithSharedResolutionDoesNotReturnLiminalInstance(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, shared: true),
        );

        $liminalInstance = $container->resolve(Resolution::for(ClassWithMethods::class)->liminal());
        $sharedInstance  = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertNotSame($liminalInstance, $sharedInstance);
    }

    // -------------------------------------------------------------------------
    // Alias resolution and storage key
    // -------------------------------------------------------------------------

    /**
     * - When a concrete class is resolved via an alias that maps it to an abstract
     *   binding, the resolved instance is stored under the abstract key so that a
     *   subsequent direct resolution of the abstract retrieves the same shared instance.
     */
    #[Test]
    public function resolveViaAliasStoresInstanceUnderAbstractKey(): void
    {
        $binding   = new Binding(AbstractInterface::class, concrete: ConcreteClass::class, shared: true);
        $container = new Container(
            new ResolverCatalogue([], GenericResolver::class),
            new BindingCatalogue(
                [AbstractInterface::class => $binding],
                [ConcreteClass::class => AbstractInterface::class],
                [],
            ),
        );

        $first  = $container->resolve(Resolution::for(ConcreteClass::class));
        $second = $container->resolve(Resolution::for(AbstractInterface::class));

        $this->assertSame($first, $second);
    }

    /**
     * - Resolving a shared binding through its alias twice returns the same instance,
     *   since the alias is normalised before the cache is read as well as written.
     */
    #[Test]
    public function resolveViaAliasTwiceReturnsSameInstance(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(AbstractInterface::class, concrete: ConcreteClass::class, shared: true),
            [ConcreteClass::class => AbstractInterface::class],
        );

        $first  = $container->resolve(Resolution::for(ConcreteClass::class));
        $second = $container->resolve(Resolution::for(ConcreteClass::class));

        $this->assertSame($first, $second);
    }

    /**
     * - Resolving the abstract and then its alias returns the same instance, the mirror
     *   of resolving the alias first.
     */
    #[Test]
    public function resolveAbstractThenAliasReturnsSameInstance(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(AbstractInterface::class, concrete: ConcreteClass::class, shared: true),
            [ConcreteClass::class => AbstractInterface::class],
        );

        $first  = $container->resolve(Resolution::for(AbstractInterface::class));
        $second = $container->resolve(Resolution::for(ConcreteClass::class));

        $this->assertSame($first, $second);
    }

    /**
     * - A binding that is not shared still produces a new instance on each resolution
     *   through its alias.
     */
    #[Test]
    public function resolveNotSharedBindingViaAliasReturnsNewInstanceEachTime(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(AbstractInterface::class, concrete: ConcreteClass::class, shared: false),
            [ConcreteClass::class => AbstractInterface::class],
        );

        $first  = $container->resolve(Resolution::for(ConcreteClass::class));
        $second = $container->resolve(Resolution::for(ConcreteClass::class));

        $this->assertNotSame($first, $second);
    }

    /**
     * - A liminal resolution through an alias is cached under the abstract in the weak
     *   cache, so a second liminal resolution through the alias finds it while alive.
     */
    #[Test]
    public function resolveLiminalViaAliasTwiceReturnsSameInstanceWhileAlive(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(AbstractInterface::class, concrete: ConcreteClass::class, shared: true),
            [ConcreteClass::class => AbstractInterface::class],
        );

        $first  = $container->resolve(Resolution::for(ConcreteClass::class)->liminal());
        $second = $container->resolve(Resolution::for(ConcreteClass::class)->liminal());

        $this->assertSame($first, $second);
    }

    /**
     * - An alias of a binding with no concrete class constructs the binding's abstract,
     *   not the alias, so an interface aliasing a bound class resolves to that class.
     */
    #[Test]
    public function resolveAliasOfBindingWithNoConcreteConstructsTheAbstract(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(ConcreteClass::class, shared: true),
            [AbstractInterface::class => ConcreteClass::class],
        );

        $result = $container->resolve(Resolution::for(AbstractInterface::class));

        $this->assertInstanceOf(ConcreteClass::class, $result);
    }

    /**
     * - An alias of an alias is replaced once, so the binding lookup finds nothing and the
     *   class the first alias points to is constructed and shared under that class.
     */
    #[Test]
    public function resolveAliasOfAliasConstructsAndSharesTheFirstTarget(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(ClassWithMethods::class, shared: true),
            [
                AbstractInterface::class => ConcreteClass::class,
                ConcreteClass::class     => ClassWithMethods::class,
            ],
        );

        $first  = $container->resolve(Resolution::for(AbstractInterface::class));
        $second = $container->resolve(Resolution::for(AbstractInterface::class));

        $this->assertInstanceOf(ConcreteClass::class, $first);
        $this->assertSame($first, $second);
    }

    /**
     * - A named resolution through an alias is cached under the abstract and the name, so
     *   a named resolution of the abstract afterwards returns the same instance.
     */
    #[Test]
    public function resolveNamedViaAliasThenAbstractReturnsSameInstance(): void
    {
        $named     = new Binding(AbstractInterface::class, concrete: ConcreteClass::class, shared: true);
        $container = $this->buildContainerWithAlias(
            new Binding(AbstractInterface::class, namedMap: ['primary' => $named]),
            [ConcreteClass::class => AbstractInterface::class],
        );

        $first  = $container->resolve(Resolution::for(ConcreteClass::class)->named('primary'));
        $second = $container->resolve(Resolution::for(AbstractInterface::class)->named('primary'));

        $this->assertSame($first, $second);
    }

    /**
     * - A named resolution through an alias with no child binding under that name throws,
     *   naming the class as it was requested rather than the abstract it normalises to.
     */
    #[Test]
    public function resolveNamedViaAliasWithNoNamedBindingNamesTheRequestedClass(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(AbstractInterface::class, concrete: ConcreteClass::class),
            [ConcreteClass::class => AbstractInterface::class],
        );

        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage(sprintf('No binding found for %s with name primary', ConcreteClass::class));

        $container->resolve(Resolution::for(ConcreteClass::class)->named('primary'));
    }

    /**
     * - A shared binding with a factory, resolved twice through its alias, invokes the
     *   factory once.
     */
    #[Test]
    public function resolveFactoryBindingViaAliasTwiceInvokesFactoryOnce(): void
    {
        $calls     = 0;
        $container = $this->buildContainerWithAlias(
            new Binding(
                AbstractInterface::class,
                factory: static function () use (&$calls): ConcreteClass {
                    ++$calls;

                    return new ConcreteClass();
                },
                shared: true,
            ),
            [ConcreteClass::class => AbstractInterface::class],
        );

        $first  = $container->resolve(Resolution::for(ConcreteClass::class));
        $second = $container->resolve(Resolution::for(ConcreteClass::class));

        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
    }

    // -------------------------------------------------------------------------
    // Parameter-level Ghost and Liminal attributes
    // -------------------------------------------------------------------------

    /**
     * - A constructor parameter decorated with #[Ghost] causes the container to route
     *   the dependency through the GhostResolver, which produces an uninitialised lazy
     *   ghost object rather than a fully resolved instance.
     */
    #[Test]
    public function resolveClassWithGhostParameterCreatesGhostProxy(): void
    {
        $container = $this->buildContainerWithGhostResolver();

        $result = $container->resolve(Resolution::for(ClassWithGhostDependency::class));

        $this->assertTrue(
            new ReflectionClass(ClassWithProperty::class)->isUninitializedLazyObject($result->dependency),
        );
    }

    /**
     * - A constructor parameter decorated with #[Ghost] on a built-in scalar type cannot
     *   be turned into a ghost object, so the GhostResolver must throw a
     *   DependencyResolutionException rather than silently producing an invalid instance.
     *   The container wraps it in one naming the parameter and constructor, with the
     *   resolver's exception as the previous.
     */
    #[Test]
    public function resolveClassWithGhostParameterOnNonClassTypeThrowsDependencyResolutionException(): void
    {
        $container = $this->buildContainerWithGhostResolver();

        try {
            $container->resolve(Resolution::for(ClassWithGhostScalarDependency::class));
            $this->fail('Expected a DependencyResolutionException');
        } catch (DependencyResolutionException $e) {
            $this->assertSame(
                sprintf('Cannot resolve the parameter "$name" of "%s::__construct".', ClassWithGhostScalarDependency::class),
                $e->getMessage(),
            );

            $previous = $e->getPrevious();

            $this->assertInstanceOf(DependencyResolutionException::class, $previous);
            $this->assertSame('Cannot create a ghost object for "string".', $previous->getMessage());
        }
    }

    /**
     * - When the type of a #[Ghost]-decorated parameter has a binding with a concrete
     *   class, the ghost must be created for the concrete rather than the abstract, so
     *   the initialised object is a valid, usable instance of the concrete type.
     */
    #[Test]
    public function resolveClassWithGhostParameterUsesConcreteclassFromBinding(): void
    {
        $container = new Container(
            new ResolverCatalogue([Ghost::class => GhostResolver::class], GenericResolver::class),
            new BindingCatalogue(
                [AbstractInterface::class => new Binding(AbstractInterface::class, concrete: ConcreteClass::class)],
                [],
                [],
            ),
        );

        $result = $container->resolve(Resolution::for(ClassWithGhostAbstractDependency::class));

        $this->assertInstanceOf(ConcreteClass::class, $result->dep);
    }

    /**
     * - A constructor parameter decorated with #[Liminal] causes the container to store
     *   the resolved dependency as a weak reference, so it is eligible for garbage
     *   collection once all other strong references are released. The parent is bound
     *   not shared, since a shared parent in the strong cache would hold the dependency
     *   alive.
     */
    #[Test]
    public function resolveClassWithLiminalParameterStoresDependencyWeakly(): void
    {
        $container = new Container(
            new ResolverCatalogue([Liminal::class => GenericResolver::class], GenericResolver::class),
            new BindingCatalogue(
                [
                    ClassWithProperty::class          => new Binding(ClassWithProperty::class, shared: true),
                    ClassWithLiminalDependency::class => new Binding(ClassWithLiminalDependency::class, shared: false),
                ],
                [],
                [],
            ),
        );

        $result     = $container->resolve(Resolution::for(ClassWithLiminalDependency::class));
        $dependency = $result->dependency;
        $weak       = \WeakReference::create($dependency);
        unset($result, $dependency);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    // -------------------------------------------------------------------------
    // Constructor auto-wiring
    // -------------------------------------------------------------------------

    /**
     * - A class whose constructor declares a typed parameter has that dependency
     *   auto-wired by the container rather than receiving a direct `new` instantiation.
     */
    #[Test]
    public function resolveClassWithConstructorDependencyAutowiresIt(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(ClassWithDependency::class));

        $this->assertInstanceOf(ClassWithDependency::class, $result);
        $this->assertInstanceOf(ClassWithMethods::class, $result->dependency);
    }

    /**
     * - All constructor parameters are resolved and injected; the container does
     *   not stop after the first dependency.
     */
    #[Test]
    public function resolveClassWithMultipleDependenciesAutowiresAllParameters(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(ClassWithMultipleDependencies::class));

        $this->assertInstanceOf(ClassWithMethods::class, $result->first);
        $this->assertInstanceOf(ClassWithProperty::class, $result->second);
    }

    /**
     * - When a constructor parameter has a built-in (non-class, non-interface) type
     *   such as string, the generic resolver falls back to the parameter's declared
     *   default value rather than attempting to resolve the type from the container.
     */
    #[Test]
    public function resolveClassWithScalarDefaultUsesDefaultValue(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(ClassWithScalarDefault::class));

        $this->assertSame('default', $result->name);
    }

    // -------------------------------------------------------------------------
    // invoke()
    // -------------------------------------------------------------------------

    /**
     * - `invoke()` is publicly accessible and returns the result of a plain callable.
     */
    #[Test]
    public function invokeCallableReturnsCallableResult(): void
    {
        $container = $this->buildContainer();

        $result = $container->invoke(Invocation::callable(static fn () => 'hello'));

        $this->assertSame('hello', $result);
    }

    /**
     * - `invoke()` calls a method on an existing object and returns its return value.
     */
    #[Test]
    public function invokeMethodOnExistingObjectCallsMethod(): void
    {
        $container = $this->buildContainer();
        $object    = new ClassWithMethods();

        $result = $container->invoke(Invocation::method($object, 'callableMethod'));

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Named and qualified binding resolution
    // -------------------------------------------------------------------------

    /**
     * - A named binding is resolved and cached under its name, so a second resolution
     *   with the same name returns the exact same instance rather than creating a new one.
     */
    #[Test]
    public function resolveWithNamedBindingCachesAndReturnsNamedInstance(): void
    {
        $namedInstance = new ClassWithMethods();
        $namedBinding  = new Binding(ClassWithMethods::class, instance: $namedInstance);
        $mainBinding   = new Binding(ClassWithMethods::class, namedMap: ['primary' => $namedBinding]);
        $container     = $this->buildContainerWith($mainBinding);

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));

        $this->assertSame($namedInstance, $first);
        $this->assertSame($first, $second);
    }

    /**
     * - A qualified binding is resolved and cached under its qualifier, so a second
     *   resolution with the same qualifier returns the exact same instance.
     */
    #[Test]
    public function resolveWithQualifiedBindingCachesAndReturnsQualifiedInstance(): void
    {
        $qualifier    = new TestQualifier();
        $qualInstance = new ClassWithMethods();
        $qualBinding  = new Binding(ClassWithMethods::class, instance: $qualInstance);
        $mainBinding  = new Binding(ClassWithMethods::class, qualifiedMap: [TestQualifier::class => $qualBinding]);
        $container    = $this->buildContainerWith($mainBinding);

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy($qualifier));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy($qualifier));

        $this->assertSame($qualInstance, $first);
        $this->assertSame($first, $second);
    }

    /**
     * - Auto-wiring a class whose constructor has a #[Named] parameter retrieves
     *   the correctly named binding from the container rather than the default one.
     */
    #[Test]
    public function resolveClassWithNamedDependencyRetrievesNamedBinding(): void
    {
        $namedInstance = new ClassWithMethods();
        $namedBinding  = new Binding(ClassWithMethods::class, instance: $namedInstance);
        $mainBinding   = new Binding(ClassWithMethods::class, namedMap: ['primary' => $namedBinding]);
        $container     = $this->buildContainerWith($mainBinding);

        $result = $container->resolve(Resolution::for(ClassWithNamedDependency::class));

        $this->assertSame($namedInstance, $result->dep);
    }

    // -------------------------------------------------------------------------
    // Error paths in resolve()
    // -------------------------------------------------------------------------

    /**
     * - A constructor parameter bearing both a #[Named] and a qualifier attribute is
     *   invalid; the container throws a DependencyResolutionException rather than
     *   attempting to resolve by one or the other arbitrarily. The wrapping exception
     *   names the parameter, and the previous says why it could not be resolved.
     */
    #[Test]
    public function resolveClassWithNamedAndQualifiedDependencyThrowsDependencyResolutionException(): void
    {
        $container = $this->buildContainer();

        try {
            $container->resolve(Resolution::for(ClassWithNamedAndQualifiedDependency::class));
            $this->fail('Expected a DependencyResolutionException');
        } catch (DependencyResolutionException $e) {
            $this->assertSame(
                sprintf('Cannot resolve the parameter "$dep" of "%s::__construct".', ClassWithNamedAndQualifiedDependency::class),
                $e->getMessage(),
            );

            $previous = $e->getPrevious();

            $this->assertInstanceOf(DependencyResolutionException::class, $previous);
            $this->assertSame('Cannot resolve a dependency using both a name and a qualifier.', $previous->getMessage());
        }
    }

    /**
     * - Resolving a class decorated with #[NoResolution] throws an
     *   UnresolvableClassException even though the class is otherwise valid,
     *   preventing accidental auto-wiring of classes that must not be instantiated.
     */
    #[Test]
    public function resolveClassWithNoResolutionAttributeThrowsUnresolvableClassException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(UnresolvableClassException::class);

        $container->resolve(Resolution::for(ClassWithNoResolution::class));
    }

    /**
     * - #[NoResolution] on an abstract applies when the abstract is resolved, even though
     *   the container would construct the binding's concrete class. The message names
     *   the class that carries the attribute.
     */
    #[Test]
    public function resolveAbstractCarryingNoResolutionThrowsUnresolvableClassException(): void
    {
        $container = $this->buildContainerWith(
            new Binding(NoResolutionInterface::class, concrete: ConcreteClass::class),
        );

        $this->expectException(UnresolvableClassException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s is marked with \'%s\', so cannot be resolved automatically.',
            NoResolutionInterface::class,
            NoResolution::class,
        ));

        $container->resolve(Resolution::for(NoResolutionInterface::class));
    }

    /**
     * - #[NoResolution] on a binding's concrete class applies when its abstract is resolved.
     */
    #[Test]
    public function resolveAbstractBoundToConcreteCarryingNoResolutionThrowsUnresolvableClassException(): void
    {
        $container = $this->buildContainerWith(
            new Binding(AbstractInterface::class, concrete: ClassWithNoResolution::class),
        );

        $this->expectException(UnresolvableClassException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s is marked with \'%s\', so cannot be resolved automatically.',
            ClassWithNoResolution::class,
            NoResolution::class,
        ));

        $container->resolve(Resolution::for(AbstractInterface::class));
    }

    /**
     * - A class carrying an attribute the container does not track resolves normally, and
     *   the attribute is never instantiated, since its constructor throws.
     */
    #[Test]
    public function resolveClassWithUntrackedAttributeDoesNotInstantiateIt(): void
    {
        $container = $this->buildContainer();

        $this->assertInstanceOf(
            ClassWithThrowingAttribute::class,
            $container->resolve(Resolution::for(ClassWithThrowingAttribute::class)),
        );
    }

    /**
     * - When both the abstract and the binding's concrete class carry #[NoResolution], the
     *   message names the abstract. The abstract is checked first, so a failure here means
     *   the order in which the two classes are consulted has changed.
     */
    #[Test]
    public function resolveAbstractAndConcreteBothCarryingNoResolutionNamesTheAbstract(): void
    {
        $container = $this->buildContainerWith(
            new Binding(NoResolutionInterface::class, concrete: ClassWithNoResolution::class),
        );

        $this->expectException(UnresolvableClassException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s is marked with \'%s\', so cannot be resolved automatically.',
            NoResolutionInterface::class,
            NoResolution::class,
        ));

        $container->resolve(Resolution::for(NoResolutionInterface::class));
    }

    /**
     * - #[NoResolution] is read from the requested class after alias normalisation, so
     *   resolving an alias of a marked abstract throws and names the abstract.
     */
    #[Test]
    public function resolveAliasOfAbstractCarryingNoResolutionThrowsUnresolvableClassException(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(NoResolutionInterface::class, concrete: ConcreteClass::class),
            [ConcreteClass::class => NoResolutionInterface::class],
        );

        $this->expectException(UnresolvableClassException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s is marked with \'%s\', so cannot be resolved automatically.',
            NoResolutionInterface::class,
            NoResolution::class,
        ));

        $container->resolve(Resolution::for(ConcreteClass::class));
    }

    /**
     * - #[NoResolution] only stops automatic construction, so a marked class bound to a
     *   factory resolves through the factory.
     */
    #[Test]
    public function resolveClassWithNoResolutionBoundToFactoryResolves(): void
    {
        $expected  = new ClassWithNoResolution();
        $container = $this->buildContainerWith(
            new Binding(ClassWithNoResolution::class, factory: static fn (): ClassWithNoResolution => $expected),
        );

        $this->assertSame($expected, $container->resolve(Resolution::for(ClassWithNoResolution::class)));
    }

    /**
     * - #[NoResolution] only stops automatic construction, so a marked class bound to an
     *   instance resolves to that instance.
     */
    #[Test]
    public function resolveClassWithNoResolutionBoundToInstanceResolves(): void
    {
        $expected  = new ClassWithNoResolution();
        $container = $this->buildContainerWith(
            new Binding(ClassWithNoResolution::class, instance: $expected),
        );

        $this->assertSame($expected, $container->resolve(Resolution::for(ClassWithNoResolution::class)));
    }

    /**
     * - #[Liminal] on an abstract applies when the abstract is resolved, so the instance is
     *   held weakly and is collected once nothing else references it.
     */
    #[Test]
    public function resolveAbstractCarryingLiminalStoresInstanceWeakly(): void
    {
        $container = $this->buildContainerWith(
            new Binding(LiminalInterface::class, concrete: ConcreteClass::class, shared: true),
        );

        $instance = $container->resolve(Resolution::for(LiminalInterface::class));
        $weak     = \WeakReference::create($instance);
        unset($instance);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    /**
     * - A class carrying both #[Lazy] and #[NoResolution] throws rather than returning a
     *   lazy proxy. #[NoResolution] is checked first, so a failure here means the order of
     *   the class attribute checks has changed.
     */
    #[Test]
    public function resolveClassWithLazyAndNoResolutionThrowsUnresolvableClassException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(UnresolvableClassException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s is marked with \'%s\', so cannot be resolved automatically.',
            LazyClassWithNoResolution::class,
            NoResolution::class,
        ));

        $container->resolve(Resolution::for(LazyClassWithNoResolution::class));
    }

    /**
     * - A binding for a class that does not exist throws when resolved, even when its
     *   concrete class exists, because the requested class is reflected for its class
     *   attributes.
     */
    #[Test]
    public function resolveBindingForNonexistentAbstractThrowsInvalidClassException(): void
    {
        $container = $this->buildContainerWith(
            new Binding('Missing\Contract', concrete: ConcreteClass::class),
        );

        $this->expectException(InvalidClassException::class);
        $this->expectExceptionMessage('The provided class Missing\Contract is not a valid class.');

        $container->resolve(Resolution::for('Missing\Contract'));
    }

    /**
     * - Resolving an interface that has no binding and cannot be instantiated throws
     *   a NotInstantiableException, surfacing a clear error instead of a generic
     *   PHP reflection failure.
     */
    #[Test]
    public function resolveNonInstantiableClassWithoutBindingThrowsNotInstantiableException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(NotInstantiableException::class);

        $container->resolve(Resolution::for(AbstractInterface::class));
    }

    /**
     * - A named resolution of a class with no binding at all throws, since the name
     *   selects nothing.
     */
    #[Test]
    public function resolveNamedResolutionWithNoBindingThrowsBindingNotFoundException(): void
    {
        $container = $this->buildContainerWith();

        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage(sprintf('No binding found for %s with name primary', ClassWithMethods::class));

        $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));
    }

    /**
     * - A qualified resolution of a class with no binding at all throws, since the
     *   qualifier selects nothing.
     */
    #[Test]
    public function resolveQualifiedResolutionWithNoBindingThrowsBindingNotFoundException(): void
    {
        $container = $this->buildContainerWith();

        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage(sprintf('No binding found for %s qualified by %s', ClassWithMethods::class, TestQualifier::class));

        $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TestQualifier()));
    }

    /**
     * - A named resolution of a bound class with no child binding under that name throws
     *   rather than falling back to constructing the class.
     */
    #[Test]
    public function resolveNamedResolutionWithNoNamedBindingThrowsBindingNotFoundException(): void
    {
        $container = $this->buildContainerWith(new Binding(ClassWithMethods::class));

        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage(sprintf('No binding found for %s with name primary', ClassWithMethods::class));

        $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));
    }

    /**
     * - A qualified resolution of a bound class with no child binding under that qualifier
     *   class throws rather than falling back to constructing the class.
     */
    #[Test]
    public function resolveQualifiedResolutionWithNoQualifiedBindingThrowsBindingNotFoundException(): void
    {
        $container = $this->buildContainerWith(new Binding(ClassWithMethods::class));

        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage(sprintf('No binding found for %s qualified by %s', ClassWithMethods::class, TestQualifier::class));

        $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TestQualifier()));
    }

    /**
     * - A failure while resolving a constructor parameter is wrapped in an exception
     *   naming the parameter and the constructor, with the original as the previous.
     */
    #[Test]
    public function resolveClassWithNamedDependencyWithNoBindingWrapsBindingNotFoundException(): void
    {
        $container = $this->buildContainerWith();

        try {
            $container->resolve(Resolution::for(ClassWithNamedDependency::class));
            $this->fail('Expected a DependencyResolutionException');
        } catch (DependencyResolutionException $e) {
            $this->assertSame(
                sprintf('Cannot resolve the parameter "$dep" of "%s::__construct".', ClassWithNamedDependency::class),
                $e->getMessage(),
            );

            $previous = $e->getPrevious();

            $this->assertInstanceOf(BindingNotFoundException::class, $previous);
            $this->assertSame(
                sprintf('No binding found for %s with name primary', ClassWithMethods::class),
                $previous->getMessage(),
            );
        }
    }

    /**
     * - A failure while resolving a closure parameter is wrapped the same way, naming the
     *   closure as reflection names it.
     */
    #[Test]
    public function invokeClosureWithUnresolvableParameterWrapsInDependencyResolutionException(): void
    {
        $container = $this->buildContainerWith();
        $closure   = static fn (AbstractInterface $dep): AbstractInterface => $dep;

        try {
            $container->invoke(Invocation::callable($closure));
            $this->fail('Expected a DependencyResolutionException');
        } catch (DependencyResolutionException $e) {
            $this->assertSame(
                sprintf('Cannot resolve the parameter "$dep" of "%s".', new ReflectionFunction($closure)->getName()),
                $e->getMessage(),
            );
            $this->assertInstanceOf(NotInstantiableException::class, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // Error paths in invoke()
    // -------------------------------------------------------------------------

    /**
     * - Invoking a non-public method throws an InvalidInvocationException, preventing
     *   the container from bypassing visibility rules via reflection.
     */
    #[Test]
    public function invokeNonPublicMethodThrowsInvalidInvocationException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(InvalidInvocationException::class);

        $container->invoke(Invocation::method(ClassWithPrivateMethod::class, 'secretMethod'));
    }

    /**
     * - Invoking a static method via a class name string returns the method's return
     *   value without needing an object instance to be resolved first.
     */
    #[Test]
    public function invokeStaticMethodReturnsResult(): void
    {
        $container = $this->buildContainer();

        $result = $container->invoke(Invocation::method(ClassWithMethods::class, 'callableStaticMethod'));

        $this->assertTrue($result);
    }

    /**
     * - Invoking a static method on a class whose constructor cannot be auto-resolved
     *   still succeeds, proving the static path returns immediately without attempting
     *   to resolve the class instance.
     *   (Kills the ReturnRemoval mutant that removes `return` before invokeArgs on the
     *   static path — without the return, the code would fall through and attempt to
     *   resolve ClassWithRequiredScalarParam, throwing DependencyResolutionException.)
     */
    #[Test]
    public function invokeStaticMethodOnUnresolvableClassReturnsResultDirectly(): void
    {
        $container = $this->buildContainer();

        $result = $container->invoke(Invocation::method(ClassWithRequiredScalarParam::class, 'staticValue'));

        $this->assertSame('static-result', $result);
    }

    /**
     * - Invoking a static method while passing an object instance throws
     *   InvalidInvocationException rather than silently discarding the object.
     */
    #[Test]
    public function invokeStaticMethodOnObjectInstanceThrows(): void
    {
        $container = $this->buildContainer();
        $object    = new ClassWithMethods();

        $this->expectException(InvalidInvocationException::class);

        $container->invoke(Invocation::method($object, 'callableStaticMethod'));
    }

    /**
     * - Invoking a non-static, non-constructor method with only a class name string
     *   causes the container to resolve an instance of that class first, then call
     *   the method on it.
     */
    #[Test]
    public function invokeInstanceMethodWithClassStringResolvesObjectFirst(): void
    {
        $container = $this->buildContainer();

        $result = $container->invoke(Invocation::method(ClassWithMethods::class, 'callableMethod'));

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // collectDependencies
    // -------------------------------------------------------------------------

    /**
     * - When a named argument matching a constructor parameter is passed to an
     *   invocation, the container uses that value directly rather than attempting
     *   to resolve the parameter from its type.
     */
    #[Test]
    public function invokeWithPreSuppliedArgumentUsesItDirectly(): void
    {
        $container = $this->buildContainer();

        $result = $container->invoke(
            Invocation::constructor(ClassWithScalarDefault::class)->with(['name' => 'custom']),
        );

        $this->assertSame('custom', $result->name);
    }

    /**
     * - When a constructor has a variadic parameter, the container stops collecting
     *   dependencies at that point rather than attempting to resolve the variadic,
     *   allowing the class to be instantiated with the variadic left empty.
     */
    #[Test]
    public function resolveClassWithVariadicParameterStopsBeforeVariadic(): void
    {
        $container = $this->buildContainer();

        $result = $container->resolve(Resolution::for(ClassWithVariadicParam::class));

        $this->assertInstanceOf(ClassWithMethods::class, $result->first);
    }

    /**
     * - When a named binding is marked as shared and has no fixed instance, the
     *   container creates the instance on first resolution and stores it in the
     *   named instance cache so that a second resolution with the same name returns
     *   the exact same object.
     */
    #[Test]
    public function resolveWithSharedNamedBindingCachesInstance(): void
    {
        $namedBinding = new Binding(ClassWithMethods::class, shared: true);
        $mainBinding  = new Binding(ClassWithMethods::class, namedMap: ['primary' => $namedBinding]);
        $container    = $this->buildContainerWith($mainBinding);

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));

        $this->assertSame($first, $second);
    }

    /**
     * - A qualified resolution that finds no cached entry must not fall through to the
     *   shared instance store; the container must produce a fresh object distinct from
     *   any previously cached non-qualified instance of the same class.
     */
    #[Test]
    public function resolveWithQualifiedResolutionDoesNotReturnNonQualifiedSharedInstance(): void
    {
        $qualifier   = new TestQualifier();
        $qualBinding = new Binding(ClassWithMethods::class, shared: true);
        $mainBinding = new Binding(ClassWithMethods::class, shared: true, qualifiedMap: [TestQualifier::class => $qualBinding]);
        $container   = $this->buildContainerWith($mainBinding);

        $shared    = $container->resolve(Resolution::for(ClassWithMethods::class));
        $qualified = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy($qualifier));

        $this->assertNotSame($shared, $qualified);
    }

    /**
     * - When two different qualifier types are both stored in the qualified instance
     *   cache, resolving with each qualifier returns the correct instance for that
     *   qualifier and not the one registered under the other qualifier.
     */
    #[Test]
    public function resolveWithQualifiedResolutionReturnsInstanceForCorrectQualifier(): void
    {
        $qualifier1  = new TestQualifier();
        $qualifier2  = new AnotherTestQualifier();
        $q1Binding   = new Binding(ClassWithMethods::class, shared: true);
        $q2Binding   = new Binding(ClassWithMethods::class, shared: true);
        $mainBinding = new Binding(ClassWithMethods::class, qualifiedMap: [
            TestQualifier::class        => $q1Binding,
            AnotherTestQualifier::class => $q2Binding,
        ]);
        $container = $this->buildContainerWith($mainBinding);

        $instance1 = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy($qualifier1));
        $instance2 = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy($qualifier2));
        $cached1   = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy($qualifier1));
        $cached2   = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy($qualifier2));

        $this->assertNotSame($instance1, $instance2);
        $this->assertSame($instance1, $cached1);
        $this->assertSame($instance2, $cached2);
    }

    /**
     * - A qualified instance is cached under the qualifier's class. Any value the
     *   qualifier carries is not part of the key, so two qualifiers of one class with
     *   different values share one instance and equals() is never consulted.
     */
    #[Test]
    public function resolveWithQualifiedResolutionCachesByQualifierClass(): void
    {
        $tagBinding  = new Binding(ClassWithMethods::class, shared: true);
        $mainBinding = new Binding(ClassWithMethods::class, qualifiedMap: [
            TaggedQualifier::class => $tagBinding,
        ]);
        $container = $this->buildContainerWith($mainBinding);

        $instanceA = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TaggedQualifier('a')));
        $instanceB = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TaggedQualifier('b')));

        $this->assertSame($instanceA, $instanceB);
    }

    /**
     * - When a named argument matching the first constructor parameter is pre-supplied,
     *   the container must continue collecting and auto-wiring the remaining parameters
     *   rather than stopping at the first pre-supplied argument.
     */
    #[Test]
    public function invokeWithPreSuppliedFirstArgumentStillResolvesRemainingParameters(): void
    {
        $container = $this->buildContainer();

        $result = $container->invoke(
            Invocation::constructor(ClassWithMixedParams::class)->with(['name' => 'hello']),
        );

        $this->assertSame('hello', $result->name);
        $this->assertInstanceOf(ClassWithMethods::class, $result->dep);
    }

    private function buildContainer(): Container
    {
        return new Container(
            new ResolverCatalogue([], GenericResolver::class),
            new BindingCatalogue([], [], []),
        );
    }

    private function buildContainerWith(Binding ...$bindings): Container
    {
        $map = [];
        foreach ($bindings as $binding) {
            $map[$binding->abstract] = $binding;
        }

        return new Container(
            new ResolverCatalogue([], GenericResolver::class),
            new BindingCatalogue($map, [], []),
        );
    }

    /**
     * @param array<class-string, class-string> $aliases
     */
    private function buildContainerWithAlias(Binding $binding, array $aliases): Container
    {
        return new Container(
            new ResolverCatalogue([], GenericResolver::class),
            new BindingCatalogue([$binding->abstract => $binding], $aliases, []),
        );
    }

    private function buildContainerWithGhostResolver(): Container
    {
        return new Container(
            new ResolverCatalogue([Ghost::class => GhostResolver::class], GenericResolver::class),
            new BindingCatalogue([], [], []),
        );
    }
}
