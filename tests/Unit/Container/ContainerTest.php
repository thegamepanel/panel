<?php
declare(strict_types=1);

namespace Tests\Unit\Container;

use Engine\Container\Attributes\Ghost;
use Engine\Container\Attributes\Liminal;
use Engine\Container\Bindings\Binding;
use Engine\Container\Bindings\BindingCatalogue;
use Engine\Container\Container;
use Engine\Container\Exceptions\DependencyResolutionException;
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
use Tests\Unit\Container\Fixtures\ClassWithVariadicParam;
use Tests\Unit\Container\Fixtures\ConcreteClass;
use Tests\Unit\Container\Fixtures\LazyClass;
use Tests\Unit\Container\Fixtures\LiminalClass;
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
     */
    #[Test]
    public function resolveClassWithGhostParameterOnNonClassTypeThrowsDependencyResolutionException(): void
    {
        $container = $this->buildContainerWithGhostResolver();

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage('string');

        $container->resolve(Resolution::for(ClassWithGhostScalarDependency::class));
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
     *   attempting to resolve by one or the other arbitrarily.
     */
    #[Test]
    public function resolveClassWithNamedAndQualifiedDependencyThrowsDependencyResolutionException(): void
    {
        $container = $this->buildContainer();

        $this->expectException(DependencyResolutionException::class);

        $container->resolve(Resolution::for(ClassWithNamedAndQualifiedDependency::class));
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

    private function buildContainerWithGhostResolver(): Container
    {
        return new Container(
            new ResolverCatalogue([Ghost::class => GhostResolver::class], GenericResolver::class),
            new BindingCatalogue([], [], []),
        );
    }
}
