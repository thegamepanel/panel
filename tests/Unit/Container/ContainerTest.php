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
use Engine\Container\Exceptions\CircularDependencyException;
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
use Tests\Unit\Container\Fixtures\AttributeQualifier;
use Tests\Unit\Container\Fixtures\ChainDependencyFirst;
use Tests\Unit\Container\Fixtures\ChainDependencySecond;
use Tests\Unit\Container\Fixtures\ChainDependencyThird;
use Tests\Unit\Container\Fixtures\ChildOfParentWithPrivateProperty;
use Tests\Unit\Container\Fixtures\ClassCountingLifecycle;
use Tests\Unit\Container\Fixtures\ClassExtendingInternalClass;
use Tests\Unit\Container\Fixtures\ClassExtendingStdClass;
use Tests\Unit\Container\Fixtures\ClassLeadingIntoCircularDependency;
use Tests\Unit\Container\Fixtures\ClassWithAbstractDependency;
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
use Tests\Unit\Container\Fixtures\ClassWithPrivateConstructor;
use Tests\Unit\Container\Fixtures\ClassWithPrivateMethod;
use Tests\Unit\Container\Fixtures\ClassWithProperty;
use Tests\Unit\Container\Fixtures\ClassWithPropertySubclass;
use Tests\Unit\Container\Fixtures\ClassWithRepeatedDependency;
use Tests\Unit\Container\Fixtures\ClassWithRequiredScalarParam;
use Tests\Unit\Container\Fixtures\ClassWithScalarDefault;
use Tests\Unit\Container\Fixtures\ClassWithThrowingAttribute;
use Tests\Unit\Container\Fixtures\ClassWithVariadicParam;
use Tests\Unit\Container\Fixtures\ConcreteClass;
use Tests\Unit\Container\Fixtures\ConcreteClassWithProperty;
use Tests\Unit\Container\Fixtures\DecoratableInterface;
use Tests\Unit\Container\Fixtures\DecoratedClass;
use Tests\Unit\Container\Fixtures\DecoratingClass;
use Tests\Unit\Container\Fixtures\EagerClassUsingGhostDependency;
use Tests\Unit\Container\Fixtures\EagerClassUsingLazyDependency;
use Tests\Unit\Container\Fixtures\GhostCircularFirst;
use Tests\Unit\Container\Fixtures\GhostCircularSecond;
use Tests\Unit\Container\Fixtures\LazyCircularFirst;
use Tests\Unit\Container\Fixtures\LazyCircularSecond;
use Tests\Unit\Container\Fixtures\LazyClass;
use Tests\Unit\Container\Fixtures\LazyClassDependingOnEager;
use Tests\Unit\Container\Fixtures\LazyClassWithNoResolution;
use Tests\Unit\Container\Fixtures\LazyConcreteClass;
use Tests\Unit\Container\Fixtures\LiminalClass;
use Tests\Unit\Container\Fixtures\LiminalInterface;
use Tests\Unit\Container\Fixtures\MutualDependencyFirst;
use Tests\Unit\Container\Fixtures\MutualDependencySecond;
use Tests\Unit\Container\Fixtures\NamedCircularFirst;
use Tests\Unit\Container\Fixtures\NamedCircularSecond;
use Tests\Unit\Container\Fixtures\NoResolutionInterface;
use Tests\Unit\Container\Fixtures\QualifiedCircularFirst;
use Tests\Unit\Container\Fixtures\QualifiedCircularSecond;
use Tests\Unit\Container\Fixtures\SelfDependentClass;
use Tests\Unit\Container\Fixtures\SelfDependentConcrete;
use Tests\Unit\Container\Fixtures\TaggedQualifier;
use Tests\Unit\Container\Fixtures\TestQualifier;
use Tests\Unit\Container\Fixtures\UnionCircularFirst;
use Tests\Unit\Container\Fixtures\UnionCircularSecond;
use WeakReference;

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
     * - A lazy resolution of a class with no properties that could trigger initialisation
     *   is constructed straight away and cached, so a second resolution returns the same
     *   instance.
     */
    #[Test]
    public function resolveWithLazilyConstructsAndCachesClassWithNoProperties(): void
    {
        $container = $this->buildContainer();

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->lazily());
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->lazily());

        $this->assertFalse(new ReflectionClass(ClassWithMethods::class)->isUninitializedLazyObject($first));
        $this->assertInstanceOf(ClassWithMethods::class, $first);
        $this->assertSame($first, $second);
    }

    /**
     * - A lazy resolution of a class with no properties runs its constructor, and its
     *   destructor runs once, when the instance is released.
     */
    #[Test]
    public function resolveWithLazilyRunsConstructorAndDestructorOnceForClassWithNoProperties(): void
    {
        ClassCountingLifecycle::$constructed = 0;
        ClassCountingLifecycle::$destructed  = 0;

        $container = $this->buildContainer();
        $result    = $container->resolve(Resolution::for(ClassCountingLifecycle::class)->lazily());

        unset($container, $result);

        $this->assertSame(1, ClassCountingLifecycle::$constructed);
        $this->assertSame(1, ClassCountingLifecycle::$destructed);
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

    /**
     * - A binding marked lazy returns an uninitialised proxy, which initialises when a
     *   property is accessed.
     */
    #[Test]
    public function resolveBindingMarkedLazyReturnsProxyThatInitialisesOnAccess(): void
    {
        $container = $this->buildContainerWith(new Binding(ClassWithProperty::class, lazily: true));
        $reflector = new ReflectionClass(ClassWithProperty::class);

        $proxy = $container->resolve(Resolution::for(ClassWithProperty::class));

        $this->assertTrue($reflector->isUninitializedLazyObject($proxy));
        $this->assertSame('initialized', $proxy->value);
        $this->assertFalse($reflector->isUninitializedLazyObject($proxy));
    }

    /**
     * - A named binding marked lazy returns a proxy, because the flag is read from the
     *   binding the name selects.
     */
    #[Test]
    public function resolveNamedBindingMarkedLazyReturnsProxy(): void
    {
        $namedBinding = new Binding(ClassWithProperty::class, lazily: true);
        $container    = $this->buildContainerWith(
            new Binding(ClassWithProperty::class, namedMap: ['primary' => $namedBinding]),
        );

        $proxy = $container->resolve(Resolution::for(ClassWithProperty::class)->named('primary'));

        $this->assertTrue(new ReflectionClass(ClassWithProperty::class)->isUninitializedLazyObject($proxy));
    }

    /**
     * - #[Lazy] on the concrete class of a binding applies when the abstract is resolved,
     *   and the proxy is made for the concrete class.
     */
    #[Test]
    public function resolveAbstractBoundToLazyConcreteReturnsProxyOfConcrete(): void
    {
        $container = $this->buildContainerWith(
            new Binding(AbstractInterface::class, concrete: LazyConcreteClass::class),
        );

        $proxy = $container->resolve(Resolution::for(AbstractInterface::class));

        $this->assertInstanceOf(LazyConcreteClass::class, $proxy);
        $this->assertTrue(new ReflectionClass(LazyConcreteClass::class)->isUninitializedLazyObject($proxy));
        $this->assertSame('initialized', $proxy->value);
    }

    /**
     * - #[Lazy] on the requested class applies to an instance binding, and the proxy
     *   initialises to the bound instance's state.
     */
    #[Test]
    public function resolveLazyClassInstanceBindingReturnsProxyOfInstance(): void
    {
        $instance        = new LazyClass();
        $instance->value = 'bound';
        $container       = $this->buildContainerWith(new Binding(LazyClass::class, instance: $instance));

        $proxy = $container->resolve(Resolution::for(LazyClass::class));

        $this->assertTrue(new ReflectionClass(LazyClass::class)->isUninitializedLazyObject($proxy));
        $this->assertSame('bound', $proxy->value);
    }

    /**
     * - #[Lazy] on the requested class applies to a factory binding, and the factory is
     *   not called until the proxy is first used.
     */
    #[Test]
    public function resolveLazyClassFactoryBindingDefersFactoryUntilFirstUse(): void
    {
        $calls     = 0;
        $container = $this->buildContainerWith(
            new Binding(
                LazyClass::class,
                factory: static function () use (&$calls): LazyClass {
                    ++$calls;

                    return new LazyClass();
                },
            ),
        );

        $proxy = $container->resolve(Resolution::for(LazyClass::class));

        $this->assertSame(0, $calls);
        $this->assertSame('initialized', $proxy->value);
        $this->assertSame(1, $calls);
    }

    /**
     * - A lazy resolution of an abstract bound to a concrete class gets a proxy of the
     *   concrete class, not of the abstract.
     */
    #[Test]
    public function resolveLazilyAbstractBoundToConcreteReturnsProxyOfConcrete(): void
    {
        $container = $this->buildContainerWith(
            new Binding(AbstractInterface::class, concrete: ConcreteClassWithProperty::class),
        );

        $proxy = $container->resolve(Resolution::for(AbstractInterface::class)->lazily());

        $this->assertInstanceOf(ConcreteClassWithProperty::class, $proxy);
        $this->assertTrue(new ReflectionClass(ConcreteClassWithProperty::class)->isUninitializedLazyObject($proxy));
    }

    /**
     * - A class whose only property is a private one on its parent still gets a proxy,
     *   because PHP defers that property even though the class does not list it.
     */
    #[Test]
    public function resolveLazilyClassWithInheritedPrivatePropertyReturnsProxy(): void
    {
        $container = $this->buildContainer();
        $reflector = new ReflectionClass(ChildOfParentWithPrivateProperty::class);

        $proxy = $container->resolve(Resolution::for(ChildOfParentWithPrivateProperty::class)->lazily());

        $this->assertTrue($reflector->isUninitializedLazyObject($proxy));
        $this->assertSame('initialized', $proxy->value());
        $this->assertFalse($reflector->isUninitializedLazyObject($proxy));
    }

    /**
     * - A lazy factory binding of a class with a private constructor gets a proxy, which
     *   ReflectionClass::isInstantiable() would wrongly reject.
     */
    #[Test]
    public function resolveLazilyFactoryBindingOfClassWithPrivateConstructorReturnsProxy(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithPrivateConstructor::class, factory: ClassWithPrivateConstructor::make(...)),
        );
        $reflector = new ReflectionClass(ClassWithPrivateConstructor::class);

        $proxy = $container->resolve(Resolution::for(ClassWithPrivateConstructor::class)->lazily());

        $this->assertTrue($reflector->isUninitializedLazyObject($proxy));
        $this->assertSame('initialized', $proxy->value);
    }

    /**
     * - A lazy resolution of a class extending stdClass gets a proxy, because stdClass is
     *   the one internal class PHP allows.
     */
    #[Test]
    public function resolveLazilyClassExtendingStdClassReturnsProxy(): void
    {
        $container = $this->buildContainer();

        $proxy = $container->resolve(Resolution::for(ClassExtendingStdClass::class)->lazily());

        $this->assertTrue(new ReflectionClass(ClassExtendingStdClass::class)->isUninitializedLazyObject($proxy));
    }

    /**
     * - A lazy factory binding of an interface throws when requested, without calling the
     *   factory. The instantiability check comes before the check for properties, so a
     *   failure here means the order changed and the factory ran eagerly.
     */
    #[Test]
    public function resolveLazilyFactoryBindingOfInterfaceThrowsWithoutCallingFactory(): void
    {
        $calls     = 0;
        $container = $this->buildContainerWith(
            new Binding(
                AbstractInterface::class,
                factory: static function () use (&$calls): ConcreteClass {
                    ++$calls;

                    return new ConcreteClass();
                },
            ),
        );

        try {
            $container->resolve(Resolution::for(AbstractInterface::class)->lazily());
            $this->fail('Expected a NotInstantiableException');
        } catch (NotInstantiableException $e) {
            $this->assertSame(sprintf('Class %s is not instantiable', AbstractInterface::class), $e->getMessage());
        }

        $this->assertSame(0, $calls);
    }

    /**
     * - A lazy resolution of a class extending an internal PHP class throws when
     *   requested, rather than PHP's own error from making the proxy.
     */
    #[Test]
    public function resolveLazilyClassExtendingInternalClassThrowsNotInstantiableException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(NotInstantiableException::class);
        $this->expectExceptionMessage(sprintf('Class %s is not instantiable', ClassExtendingInternalClass::class));

        $container->resolve(Resolution::for(ClassExtendingInternalClass::class)->lazily());
    }

    /**
     * - A lazy resolution of a class carrying #[NoResolution] throws when requested, not
     *   when its proxy is first used.
     */
    #[Test]
    public function resolveLazilyClassWithNoResolutionThrowsWhenRequested(): void
    {
        $container = $this->buildContainer();

        $this->expectException(UnresolvableClassException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s is marked with \'%s\', so cannot be resolved automatically.',
            ClassWithNoResolution::class,
            NoResolution::class,
        ));

        $container->resolve(Resolution::for(ClassWithNoResolution::class)->lazily());
    }

    /**
     * - A lazy resolution of an interface carrying #[NoResolution] throws
     *   UnresolvableClassException, not NotInstantiableException. #[NoResolution] is
     *   checked first, so a failure here means the order of the lazy step's checks changed.
     */
    #[Test]
    public function resolveLazilyInterfaceWithNoResolutionThrowsUnresolvableClassException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(UnresolvableClassException::class);
        $this->expectExceptionMessage(sprintf(
            'The class %s is marked with \'%s\', so cannot be resolved automatically.',
            NoResolutionInterface::class,
            NoResolution::class,
        ));

        $container->resolve(Resolution::for(NoResolutionInterface::class)->lazily());
    }

    /**
     * - A lazy resolution of an abstract bound to a concrete class carrying #[NoResolution]
     *   throws naming the concrete class.
     */
    #[Test]
    public function resolveLazilyAbstractBoundToConcreteWithNoResolutionNamesConcrete(): void
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

        $container->resolve(Resolution::for(AbstractInterface::class)->lazily());
    }

    /**
     * - A lazy factory binding of a class carrying #[NoResolution] resolves, because the
     *   attribute only stops the container constructing the class itself.
     */
    #[Test]
    public function resolveLazilyFactoryBindingOfClassWithNoResolutionReturnsFactoryInstance(): void
    {
        $instance  = new ClassWithNoResolution();
        $container = $this->buildContainerWith(
            new Binding(ClassWithNoResolution::class, factory: static fn () => $instance),
        );

        $result = $container->resolve(Resolution::for(ClassWithNoResolution::class)->lazily());

        $this->assertSame($instance, $result);
    }

    /**
     * - A lazy instance binding of a class carrying #[NoResolution] resolves, because the
     *   attribute only stops the container constructing the class itself.
     */
    #[Test]
    public function resolveLazilyInstanceBindingOfClassWithNoResolutionReturnsInstance(): void
    {
        $instance  = new ClassWithNoResolution();
        $container = $this->buildContainerWith(new Binding(ClassWithNoResolution::class, instance: $instance));

        $result = $container->resolve(Resolution::for(ClassWithNoResolution::class)->lazily());

        $this->assertSame($instance, $result);
    }

    /**
     * - A lazy factory binding that returns its own class initialises without throwing,
     *   so the proxy's check accepts an instance of exactly the proxy's class.
     */
    #[Test]
    public function resolveLazilyFactoryBindingReturningItsOwnClassInitialises(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithProperty::class, factory: static fn () => new ClassWithProperty()),
        );

        $proxy = $container->resolve(Resolution::for(ClassWithProperty::class)->lazily());

        $this->assertSame('initialized', $proxy->value);
    }

    /**
     * - A lazy factory binding that returns an instance of a subclass gets a proxy, which
     *   throws when first used, because PHP does not accept a subclass behind a proxy.
     */
    #[Test]
    public function resolveLazilyFactoryBindingReturningSubclassThrowsOnFirstUse(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithProperty::class, factory: static fn () => new ClassWithPropertySubclass()),
        );

        $proxy = $container->resolve(Resolution::for(ClassWithProperty::class)->lazily());

        $this->assertTrue(new ReflectionClass(ClassWithProperty::class)->isUninitializedLazyObject($proxy));

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot initialise the lazy proxy for "%s" with an instance of "%s".',
            ClassWithProperty::class,
            ClassWithPropertySubclass::class,
        ));

        $proxy->value;
    }

    /**
     * - After a proxy fails because its factory returned a subclass, a second lazy request
     *   returns the real instance, which was cached before the check threw.
     */
    #[Test]
    public function resolveLazilyAfterFailedProxyReturnsCachedInstance(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithProperty::class, factory: static fn () => new ClassWithPropertySubclass()),
        );

        $proxy = $container->resolve(Resolution::for(ClassWithProperty::class)->lazily());

        try {
            $proxy->value;
            $this->fail('Expected a DependencyResolutionException');
        } catch (DependencyResolutionException) {
        }

        $result = $container->resolve(Resolution::for(ClassWithProperty::class)->lazily());

        $this->assertInstanceOf(ClassWithPropertySubclass::class, $result);
        $this->assertFalse(new ReflectionClass(ClassWithPropertySubclass::class)->isUninitializedLazyObject($result));
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
     * - A binding marked liminal makes a resolution liminal without the resolution asking,
     *   so a second resolve finds the instance while it is alive, and it is collected once
     *   nothing else references it.
     */
    #[Test]
    public function resolveWithLiminalBindingReturnsSameInstanceWhileAlive(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, liminal: true, shared: true),
        );

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertSame($first, $second);

        $weak = WeakReference::create($first);
        unset($first, $second);
        gc_collect_cycles();

        $this->assertNull($weak->get());
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
        $weak     = WeakReference::create($instance);
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
     * - A class carrying `#[Liminal]` makes a resolution liminal without the resolution
     *   asking, so a second resolve finds the instance while it is alive, and it is
     *   collected once nothing else references it.
     */
    #[Test]
    public function resolveLiminalClassReturnsSameInstanceWhileAlive(): void
    {
        $container = $this->buildContainerWith(
            new Binding(LiminalClass::class, shared: true),
        );

        $first  = $container->resolve(Resolution::for(LiminalClass::class));
        $second = $container->resolve(Resolution::for(LiminalClass::class));

        $this->assertSame($first, $second);

        $weak = WeakReference::create($first);
        unset($first, $second);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    /**
     * - `#[Liminal]` on the concrete class of a binding applies when its abstract is
     *   resolved, so a second resolve finds the instance while it is alive, and it is
     *   collected once nothing else references it.
     */
    #[Test]
    public function resolveAbstractWithLiminalConcreteReturnsSameInstanceWhileAlive(): void
    {
        $container = $this->buildContainerWith(
            new Binding(AbstractInterface::class, concrete: LiminalClass::class, shared: true),
        );

        $first  = $container->resolve(Resolution::for(AbstractInterface::class));
        $second = $container->resolve(Resolution::for(AbstractInterface::class));

        $this->assertSame($first, $second);

        $weak = WeakReference::create($first);
        unset($first, $second);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    /**
     * - `#[Liminal]` on the concrete class applies when the concrete is resolved through its
     *   alias, sharing one entry with resolutions of the abstract, and the instance is
     *   collected once nothing else references it.
     */
    #[Test]
    public function resolveLiminalConcreteViaAliasSharesEntryWithAbstract(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(AbstractInterface::class, concrete: LiminalClass::class, shared: true),
            [LiminalClass::class => AbstractInterface::class],
        );

        $first  = $container->resolve(Resolution::for(LiminalClass::class));
        $second = $container->resolve(Resolution::for(AbstractInterface::class));

        $this->assertSame($first, $second);

        $weak = WeakReference::create($first);
        unset($first, $second);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    /**
     * - `#[Liminal]` on the requested class applies to a factory binding, even though the
     *   container never constructs the class, so the factory's instance is shared while
     *   it is alive and collected once nothing else references it.
     */
    #[Test]
    public function resolveFactoryBindingForLiminalClassReturnsSameInstanceWhileAlive(): void
    {
        $container = $this->buildContainerWith(
            new Binding(LiminalClass::class, factory: fn () => new LiminalClass(), shared: true),
        );

        $first  = $container->resolve(Resolution::for(LiminalClass::class));
        $second = $container->resolve(Resolution::for(LiminalClass::class));

        $this->assertSame($first, $second);

        $weak = WeakReference::create($first);
        unset($first, $second);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    /**
     * - A binding marked liminal but not shared returns a new instance each time, since
     *   liminality only chooses the cache and never turns sharing on.
     */
    #[Test]
    public function resolveWithUnsharedLiminalBindingReturnsNewInstanceEachTime(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, liminal: true, shared: false),
        );

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class));

        $this->assertNotSame($first, $second);
    }

    /**
     * - A named child binding marked liminal makes a named resolution liminal without the
     *   resolution asking, so a second resolve finds the instance while it is alive, and
     *   it is collected once nothing else references it.
     */
    #[Test]
    public function resolveWithNamedLiminalBindingReturnsSameInstanceWhileAlive(): void
    {
        $namedBinding = new Binding(ClassWithMethods::class, liminal: true, shared: true);
        $mainBinding  = new Binding(ClassWithMethods::class, namedMap: ['primary' => $namedBinding]);
        $container    = $this->buildContainerWith($mainBinding);

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));

        $this->assertSame($first, $second);

        $weak = WeakReference::create($first);
        unset($first, $second);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    /**
     * - A qualified child binding marked liminal makes a qualified resolution liminal
     *   without the resolution asking, so a second resolve finds the instance while it is
     *   alive, and it is collected once nothing else references it.
     */
    #[Test]
    public function resolveWithQualifiedLiminalBindingReturnsSameInstanceWhileAlive(): void
    {
        $qualBinding = new Binding(ClassWithMethods::class, liminal: true, shared: true);
        $mainBinding = new Binding(ClassWithMethods::class, qualifiedMap: [TestQualifier::class => $qualBinding]);
        $container   = $this->buildContainerWith($mainBinding);

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TestQualifier()));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TestQualifier()));

        $this->assertSame($first, $second);

        $weak = WeakReference::create($first);
        unset($first, $second);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    /**
     * - A named child binding does not take liminality from its parent binding, so a
     *   named resolution of a non-liminal child is held strongly and is not collected.
     */
    #[Test]
    public function resolveWithNamedBindingDoesNotInheritLiminalFromParent(): void
    {
        $namedBinding = new Binding(ClassWithMethods::class, shared: true);
        $mainBinding  = new Binding(ClassWithMethods::class, namedMap: ['primary' => $namedBinding], liminal: true);
        $container    = $this->buildContainerWith($mainBinding);

        $first  = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));
        $second = $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary'));

        $this->assertSame($first, $second);

        $weak = WeakReference::create($first);
        unset($first, $second);
        gc_collect_cycles();

        $this->assertNotNull($weak->get());
    }

    /**
     * - A resolution of a liminal binding and a liminal resolution of the same binding
     *   share one entry, since both work out as liminal before the cache is read.
     */
    #[Test]
    public function resolveWithLiminalBindingSharesEntryWithLiminalResolution(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, liminal: true, shared: true),
        );

        $plain   = $container->resolve(Resolution::for(ClassWithMethods::class));
        $liminal = $container->resolve(Resolution::for(ClassWithMethods::class)->liminal());

        $this->assertSame($plain, $liminal);
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
        $weak       = WeakReference::create($dependency);
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
        $weak     = WeakReference::create($instance);
        unset($instance);
        gc_collect_cycles();

        $this->assertNull($weak->get());
    }

    /**
     * - A class carrying both #[Lazy] and #[NoResolution] throws rather than returning a
     *   lazy proxy. The lazy step checks #[NoResolution] before making a proxy, so a
     *   failure here means the order of the lazy step's checks has changed.
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
     * - A factory binding for a class that does not exist throws when resolved, even
     *   though the container never constructs the class, because the requested class is
     *   reflected for `#[Liminal]` before the cache is checked.
     */
    #[Test]
    public function resolveFactoryBindingForNonexistentAbstractThrowsInvalidClassException(): void
    {
        $container = $this->buildContainerWith(
            new Binding('Missing\Contract', factory: fn () => new ClassWithMethods()),
        );

        $this->expectException(InvalidClassException::class);
        $this->expectExceptionMessage('The provided class Missing\Contract is not a valid class.');

        $container->resolve(Resolution::for('Missing\Contract'));
    }

    /**
     * - An instance binding for a class that does not exist throws when resolved, because
     *   the requested class is reflected for `#[Liminal]` before the cache is checked.
     */
    #[Test]
    public function resolveInstanceBindingForNonexistentAbstractThrowsInvalidClassException(): void
    {
        $container = $this->buildContainerWith(
            new Binding('Missing\Contract', instance: new ClassWithMethods()),
        );

        $this->expectException(InvalidClassException::class);
        $this->expectExceptionMessage('The provided class Missing\Contract is not a valid class.');

        $container->resolve(Resolution::for('Missing\Contract'));
    }

    /**
     * - A liminal resolution of a factory binding for a class that does not exist throws,
     *   even though its own flag means the class is not reflected for `#[Liminal]`,
     *   because the class is still reflected for `#[Lazy]`.
     */
    #[Test]
    public function resolveLiminalResolutionOfFactoryBindingForNonexistentAbstractThrowsInvalidClassException(): void
    {
        $container = $this->buildContainerWith(
            new Binding('Missing\Contract', factory: fn () => new ClassWithMethods()),
        );

        $this->expectException(InvalidClassException::class);
        $this->expectExceptionMessage('The provided class Missing\Contract is not a valid class.');

        $container->resolve(Resolution::for('Missing\Contract')->liminal());
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
     * - A lazy named resolution of a class with no binding throws when resolved, not when
     *   its proxy is first used. A failure here means the binding lookup has moved below
     *   the lazy proxy check.
     */
    #[Test]
    public function resolveLazyNamedResolutionWithNoBindingThrowsBindingNotFoundException(): void
    {
        $container = $this->buildContainerWith();

        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage(sprintf('No binding found for %s with name primary', ClassWithMethods::class));

        $container->resolve(Resolution::for(ClassWithMethods::class)->named('primary')->lazily());
    }

    /**
     * - A lazy qualified resolution of a class with no binding throws when resolved, not
     *   when its proxy is first used. A failure here means the binding lookup has moved
     *   below the lazy proxy check.
     */
    #[Test]
    public function resolveLazyQualifiedResolutionWithNoBindingThrowsBindingNotFoundException(): void
    {
        $container = $this->buildContainerWith();

        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage(sprintf('No binding found for %s qualified by %s', ClassWithMethods::class, TestQualifier::class));

        $container->resolve(Resolution::for(ClassWithMethods::class)->qualifiedBy(new TestQualifier())->lazily());
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
    // Circular dependencies
    // -------------------------------------------------------------------------

    /**
     * - A class whose constructor requires itself throws a circular dependency naming
     *   it twice, rather than exhausting the call stack.
     */
    #[Test]
    public function resolveSelfDependentClassThrowsCircularDependencyException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%1$s".',
            SelfDependentClass::class,
        ));

        $container->resolve(Resolution::for(SelfDependentClass::class));
    }

    /**
     * - Two classes whose constructors require each other throw a circular dependency,
     *   unwrapped by the parameter resolution of either constructor.
     */
    #[Test]
    public function resolveMutuallyDependentClassesThrowsCircularDependencyException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" -> "%1$s".',
            MutualDependencyFirst::class,
            MutualDependencySecond::class,
        ));

        $container->resolve(Resolution::for(MutualDependencyFirst::class));
    }

    /**
     * - A circular dependency through three classes names all four entries in order,
     *   from the first appearance of the repeated entry to its repetition.
     */
    #[Test]
    public function resolveChainOfThreeThrowsCircularDependencyNamingEveryEntry(): void
    {
        $container = $this->buildContainer();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" -> "%3$s" -> "%1$s".',
            ChainDependencyFirst::class,
            ChainDependencySecond::class,
            ChainDependencyThird::class,
        ));

        $container->resolve(Resolution::for(ChainDependencyFirst::class));
    }

    /**
     * - The chain starts at the first appearance of the repeated entry, so a class that
     *   only leads into the circular dependency is not named.
     */
    #[Test]
    public function resolveClassLeadingIntoCircularDependencyOmitsItFromTheChain(): void
    {
        $container = $this->buildContainer();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" -> "%1$s".',
            MutualDependencyFirst::class,
            MutualDependencySecond::class,
        ));

        $container->resolve(Resolution::for(ClassLeadingIntoCircularDependency::class));
    }

    /**
     * - A named entry in the chain is reported with its name.
     */
    #[Test]
    public function resolveCircularDependencyThroughNamedBindingNamesTheEntry(): void
    {
        $container = $this->buildContainerWith(
            new Binding(
                NamedCircularSecond::class,
                namedMap: ['inner' => new Binding(NamedCircularSecond::class)],
            ),
        );

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" named "inner" -> "%1$s".',
            NamedCircularFirst::class,
            NamedCircularSecond::class,
        ));

        $container->resolve(Resolution::for(NamedCircularFirst::class));
    }

    /**
     * - A qualified entry in the chain is reported with its qualifier class.
     */
    #[Test]
    public function resolveCircularDependencyThroughQualifiedBindingNamesTheQualifier(): void
    {
        $container = $this->buildContainerWith(
            new Binding(
                QualifiedCircularSecond::class,
                qualifiedMap: [AttributeQualifier::class => new Binding(QualifiedCircularSecond::class)],
            ),
        );

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" qualified by "%3$s" -> "%1$s".',
            QualifiedCircularFirst::class,
            QualifiedCircularSecond::class,
            AttributeQualifier::class,
        ));

        $container->resolve(Resolution::for(QualifiedCircularFirst::class));
    }

    /**
     * - An entry is identified by the class after alias normalisation, so a concrete
     *   class requiring itself through its alias is reported under the abstract.
     */
    #[Test]
    public function resolveCircularDependencyThroughAliasNamesTheAbstract(): void
    {
        $container = $this->buildContainerWithAlias(
            new Binding(AbstractInterface::class, concrete: SelfDependentConcrete::class),
            [SelfDependentConcrete::class => AbstractInterface::class],
        );

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%1$s".',
            AbstractInterface::class,
        ));

        $container->resolve(Resolution::for(AbstractInterface::class));
    }

    /**
     * - A factory whose parameters require its own abstract is a circular dependency,
     *   because the entry is added before the factory is invoked.
     */
    #[Test]
    public function resolveFactoryRequiringItsOwnAbstractThrowsCircularDependencyException(): void
    {
        $container = $this->buildContainerWith(
            new Binding(
                AbstractInterface::class,
                factory: static fn (ClassWithAbstractDependency $dependency): ConcreteClass => new ConcreteClass(),
            ),
        );

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" -> "%1$s".',
            AbstractInterface::class,
            ClassWithAbstractDependency::class,
        ));

        $container->resolve(Resolution::for(AbstractInterface::class));
    }

    /**
     * - A circular dependency reached through a union-typed parameter is not wrapped as
     *   a union failure, because the generic resolver rethrows it unchanged.
     */
    #[Test]
    public function resolveCircularDependencyThroughUnionParameterThrowsCircularDependencyException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" -> "%1$s".',
            UnionCircularFirst::class,
            UnionCircularSecond::class,
        ));

        $container->resolve(Resolution::for(UnionCircularFirst::class));
    }

    /**
     * - A decorator requiring the named binding of its own abstract is not a circular
     *   dependency, because the named resolution is a different entry.
     */
    #[Test]
    public function resolveDecoratorRequiringNamedBindingOfItsAbstractResolves(): void
    {
        $container = $this->buildContainerWith(
            new Binding(
                DecoratableInterface::class,
                concrete: DecoratingClass::class,
                namedMap: ['inner' => new Binding(DecoratableInterface::class, concrete: DecoratedClass::class)],
            ),
        );

        $result = $container->resolve(Resolution::for(DecoratableInterface::class));

        $this->assertInstanceOf(DecoratingClass::class, $result);
        $this->assertInstanceOf(DecoratedClass::class, $result->inner);
    }

    /**
     * - Two classes carrying #[Lazy] that require each other resolve, because each
     *   request returns a proxy before any entry is added, and each proxy initialises
     *   when a property is read.
     */
    #[Test]
    public function resolveMutuallyDependentLazyClassesResolvesAndInitialisesOnAccess(): void
    {
        $container = $this->buildContainer();

        $first = $container->resolve(Resolution::for(LazyCircularFirst::class));

        $this->assertTrue(new ReflectionClass(LazyCircularFirst::class)->isUninitializedLazyObject($first));

        $second = $first->second;

        $this->assertFalse(new ReflectionClass(LazyCircularFirst::class)->isUninitializedLazyObject($first));
        $this->assertTrue(new ReflectionClass(LazyCircularSecond::class)->isUninitializedLazyObject($second));

        $back = $second->first;

        $this->assertFalse(new ReflectionClass(LazyCircularSecond::class)->isUninitializedLazyObject($second));
        $this->assertInstanceOf(LazyCircularFirst::class, $back);
        $this->assertFalse(new ReflectionClass(LazyCircularFirst::class)->isUninitializedLazyObject($back));
    }

    /**
     * - A lazy proxy initialised inside the constructor that requested it is a circular
     *   dependency, because that constructor's entry is still on the stack.
     */
    #[Test]
    public function resolveClassInitialisingLazyDependencyInItsConstructorThrowsCircularDependencyException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" -> "%1$s".',
            EagerClassUsingLazyDependency::class,
            LazyClassDependingOnEager::class,
        ));

        $container->resolve(Resolution::for(EagerClassUsingLazyDependency::class));
    }

    /**
     * - A lazy request for a resolution already on the stack returns a proxy instead of
     *   throwing. If this fails, the entry is being added before the lazy proxy step.
     */
    #[Test]
    public function resolveLazilyWhileSameResolutionIsOnTheStackReturnsProxy(): void
    {
        $proxy     = null;
        $container = null;
        $container = $this->buildContainerWith(
            new Binding(
                ClassWithProperty::class,
                factory: static function () use (&$container, &$proxy): ClassWithProperty {
                    $proxy = $container->resolve(Resolution::for(ClassWithProperty::class)->lazily());

                    return new ClassWithProperty();
                },
            ),
        );

        $container->resolve(Resolution::for(ClassWithProperty::class));

        $this->assertInstanceOf(ClassWithProperty::class, $proxy);
        $this->assertTrue(new ReflectionClass(ClassWithProperty::class)->isUninitializedLazyObject($proxy));
    }

    /**
     * - A lazy request for a class with no properties that is already on the stack is a
     *   circular dependency, because such a class is given no proxy and is constructed.
     */
    #[Test]
    public function resolveLazilyClassWithNoPropertiesWhileOnTheStackThrowsCircularDependencyException(): void
    {
        $container = null;
        $container = $this->buildContainerWith(
            new Binding(
                ClassWithMethods::class,
                factory: static function () use (&$container): ClassWithMethods {
                    $container->resolve(Resolution::for(ClassWithMethods::class)->lazily());

                    return new ClassWithMethods();
                },
            ),
        );

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%1$s".',
            ClassWithMethods::class,
        ));

        $container->resolve(Resolution::for(ClassWithMethods::class));
    }

    /**
     * - A #[Ghost] parameter breaks a circular dependency, because the ghost's
     *   constructor runs on first access, after the class requiring it is cached.
     */
    #[Test]
    public function resolveCircularDependencyThroughGhostParameterResolves(): void
    {
        $container = $this->buildContainerWithGhostResolver();

        $first = $container->resolve(Resolution::for(GhostCircularFirst::class));

        $this->assertTrue(new ReflectionClass(GhostCircularSecond::class)->isUninitializedLazyObject($first->second));
        $this->assertSame($first, $first->second->first);
    }

    /**
     * - A ghost initialised inside the constructor that requested it is a circular
     *   dependency, because that constructor's entry is still on the stack. The ghost
     *   adds no entry of its own, so the chain names only the requesting class.
     */
    #[Test]
    public function resolveClassInitialisingGhostDependencyInItsConstructorThrowsCircularDependencyException(): void
    {
        $container = $this->buildContainerWithGhostResolver();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage(sprintf(
            'Circular dependency detected: "%1$s" -> "%1$s".',
            EagerClassUsingGhostDependency::class,
        ));

        $container->resolve(Resolution::for(EagerClassUsingGhostDependency::class));
    }

    /**
     * - A resolution that throws removes its entry, so resolving the same class again
     *   fails for the same reason rather than as a circular dependency.
     */
    #[Test]
    public function resolveAfterFailedResolutionIsNotReportedAsCircular(): void
    {
        $container = $this->buildContainer();
        $message   = sprintf(
            'Cannot resolve the parameter "$value" of "%s::__construct".',
            ClassWithRequiredScalarParam::class,
        );

        foreach ([1, 2] as $attempt) {
            try {
                $container->resolve(Resolution::for(ClassWithRequiredScalarParam::class));
                $this->fail('Expected a DependencyResolutionException on attempt ' . $attempt);
            } catch (DependencyResolutionException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    /**
     * - A circular dependency removes every entry as it unwinds, so a second attempt
     *   reports the same chain rather than a longer one.
     */
    #[Test]
    public function resolveCircularDependencyTwiceReportsTheSameChain(): void
    {
        $container = $this->buildContainer();
        $message   = sprintf(
            'Circular dependency detected: "%1$s" -> "%2$s" -> "%1$s".',
            MutualDependencyFirst::class,
            MutualDependencySecond::class,
        );

        foreach ([1, 2] as $attempt) {
            try {
                $container->resolve(Resolution::for(MutualDependencyFirst::class));
                $this->fail('Expected a CircularDependencyException on attempt ' . $attempt);
            } catch (CircularDependencyException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    /**
     * - The same class resolved for two parameters of one constructor is not a circular
     *   dependency, because the first resolution leaves the stack before the second.
     *   The binding is not shared, so both are constructed rather than one cached.
     */
    #[Test]
    public function resolveSameClassForTwoParametersIsNotCircular(): void
    {
        $container = $this->buildContainerWith(
            new Binding(ClassWithMethods::class, shared: false),
        );

        $result = $container->resolve(Resolution::for(ClassWithRepeatedDependency::class));

        $this->assertNotSame($result->first, $result->second);
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
