<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Resolvers;

use Countable;
use Engine\Container\Attributes\Named;
use Engine\Container\Bindings\Binding;
use Engine\Container\Bindings\BindingCatalogue;
use Engine\Container\Container;
use Engine\Container\Contracts\Qualifier;
use Engine\Container\Dependency;
use Engine\Container\Exceptions\DependencyResolutionException;
use Engine\Container\Resolvers\GenericResolver;
use Engine\Container\Resolvers\ResolverCatalogue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;
use Stringable;
use Tests\Unit\Container\Fixtures\AbstractInterface;
use Tests\Unit\Container\Fixtures\ClassWithAbstractUnionParam;
use Tests\Unit\Container\Fixtures\ClassWithDependency;
use Tests\Unit\Container\Fixtures\ClassWithDnfUnionParam;
use Tests\Unit\Container\Fixtures\ClassWithIntersectionParam;
use Tests\Unit\Container\Fixtures\ClassWithMethods;
use Tests\Unit\Container\Fixtures\ClassWithMixedUnionParam;
use Tests\Unit\Container\Fixtures\ClassWithMultiClassUnionParam;
use Tests\Unit\Container\Fixtures\ClassWithNullableAbstractUnionParam;
use Tests\Unit\Container\Fixtures\ClassWithNullableMultiClassUnionParam;
use Tests\Unit\Container\Fixtures\ClassWithNullableScalarParam;
use Tests\Unit\Container\Fixtures\ClassWithRequiredScalarParam;
use Tests\Unit\Container\Fixtures\StringableCountable;
use Tests\Unit\Container\Fixtures\TestQualifier;

#[Group('unit'), Group('container'), Group('generic-resolver')]
class GenericResolverTest extends TestCase
{
    // -------------------------------------------------------------------------
    // resolve() dispatch — no type
    // -------------------------------------------------------------------------

    /**
     * - A dependency with no type at all and a declared default returns that default
     *   rather than attempting resolution, covering the untyped-with-default path.
     */
    #[Test]
    public function resolveWithNoTypeAndDefaultReturnsDefault(): void
    {
        $resolver   = new GenericResolver();
        $dependency = new Dependency('param', null, hasDefault: true, default: 'fallback');

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertSame('fallback', $result);
    }

    /**
     * - A dependency with no type and no default cannot be resolved, so the resolver
     *   throws a DependencyResolutionException rather than returning a null silently.
     */
    #[Test]
    public function resolveWithNoTypeAndNoDefaultThrowsDependencyResolutionException(): void
    {
        $resolver   = new GenericResolver();
        $dependency = new Dependency('param', null);

        $this->expectException(DependencyResolutionException::class);

        $resolver->resolve($dependency, $this->buildContainer());
    }

    // -------------------------------------------------------------------------
    // resolveSingleType — class/interface type
    // -------------------------------------------------------------------------

    /**
     * - A dependency typed as a concrete class that exists is resolved through the
     *   container's auto-wiring, returning a real instance of that class.
     */
    #[Test]
    public function resolveWithNamedClassTypeResolvesViaContainer(): void
    {
        $resolver = new GenericResolver();
        // ClassWithDependency::$dependency is typed as ClassWithMethods — a named class type
        $dependency = $this->dependencyFrom(ClassWithDependency::class, 'dependency');

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertInstanceOf(ClassWithMethods::class, $result);
    }

    // -------------------------------------------------------------------------
    // resolveSingleType — scalar types
    // -------------------------------------------------------------------------

    /**
     * - A scalar-typed dependency (string) that has a default value returns the
     *   default rather than throwing, since built-in types cannot be auto-wired.
     */
    #[Test]
    public function resolveWithScalarTypeAndDefaultReturnsDefault(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithRequiredScalarParam::class, 'value', hasDefault: true, default: 'hello');

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertSame('hello', $result);
    }

    /**
     * - A nullable scalar-typed dependency (string) with no default returns null
     *   rather than throwing, since the type explicitly permits null.
     */
    #[Test]
    public function resolveWithNullableScalarTypeReturnsNull(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithNullableScalarParam::class, 'value');

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertNull($result);
    }

    /**
     * - A required, non-nullable scalar-typed dependency with no default cannot be
     *   resolved, so the resolver throws a DependencyResolutionException naming the type.
     */
    #[Test]
    public function resolveWithRequiredScalarTypeThrowsDependencyResolutionException(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithRequiredScalarParam::class, 'value');

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage('string');

        $resolver->resolve($dependency, $this->buildContainer());
    }

    // -------------------------------------------------------------------------
    // resolveIntersectionType
    // -------------------------------------------------------------------------

    /**
     * - An intersection-typed dependency with no bindings registered for any of
     *   its constituent types throws DependencyResolutionException::intersectionNoBinding.
     */
    #[Test]
    public function resolveIntersectionTypeWithNoBindingsThrowsIntersectionNoBinding(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage('without a binding');

        $resolver->resolve($dependency, $this->buildContainer());
    }

    /**
     * - An intersection-typed dependency with no bindings but a declared default
     *   returns that default instead of throwing.
     */
    #[Test]
    public function resolveIntersectionTypeWithNoBindingsAndDefaultReturnsDefault(): void
    {
        $resolver   = new GenericResolver();
        $fallback   = new StringableCountable();
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep', hasDefault: true, default: $fallback);

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertSame($fallback, $result);
    }

    /**
     * - An intersection-typed dependency where one of the constituent types has a
     *   binding whose resolved instance satisfies the full intersection is returned
     *   as the result.
     */
    #[Test]
    public function resolveIntersectionTypeWithSatisfyingBindingReturnsInstance(): void
    {
        $resolver   = new GenericResolver();
        $instance   = new StringableCountable();
        $binding    = new Binding(Stringable::class, instance: $instance);
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $result = $resolver->resolve($dependency, $this->buildContainer($binding));

        $this->assertSame($instance, $result);
    }

    /**
     * - An intersection-typed dependency where a binding is found but the resolved
     *   instance does not satisfy every type in the intersection throws
     *   DependencyResolutionException::intersection.
     */
    #[Test]
    public function resolveIntersectionTypeWithUnsatisfyingBindingThrowsDependencyResolutionException(): void
    {
        $resolver   = new GenericResolver();
        $instance   = new ClassWithMethods(); // does not implement Countable
        $binding    = new Binding(Stringable::class, instance: $instance);
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $this->expectException(DependencyResolutionException::class);

        $resolver->resolve($dependency, $this->buildContainer($binding));
    }

    /**
     * - An intersection-typed dependency where no binding produces a satisfying
     *   instance but a default is declared returns that default instead of throwing.
     */
    #[Test]
    public function resolveIntersectionTypeWithUnsatisfyingBindingAndDefaultReturnsDefault(): void
    {
        $resolver   = new GenericResolver();
        $fallback   = new StringableCountable();
        $instance   = new ClassWithMethods(); // does not implement Countable
        $binding    = new Binding(Stringable::class, instance: $instance);
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep', hasDefault: true, default: $fallback);

        $result = $resolver->resolve($dependency, $this->buildContainer($binding));

        $this->assertSame($fallback, $result);
    }

    // -------------------------------------------------------------------------
    // resolveUnionType
    // -------------------------------------------------------------------------

    /**
     * - A union-typed dependency containing exactly one resolvable class type alongside
     *   a built-in scalar (ClassWithMethods|string) resolves the single class type via
     *   the container, ignoring the non-resolvable scalar.
     */
    #[Test]
    public function resolveUnionTypeWithSingleResolvableClassResolvesIt(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithMixedUnionParam::class, 'dep');

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertInstanceOf(ClassWithMethods::class, $result);
    }

    /**
     * - A DNF union-typed dependency ((Stringable&Countable)|null) containing an
     *   intersection subtype as the single resolvable entry resolves the intersection
     *   via the container when a satisfying binding exists.
     */
    #[Test]
    public function resolveUnionTypeWithSingleIntersectionSubtypeResolvesIt(): void
    {
        $resolver   = new GenericResolver();
        $instance   = new StringableCountable();
        $binding    = new Binding(Stringable::class, instance: $instance);
        $dependency = $this->dependencyFrom(ClassWithDnfUnionParam::class, 'dep');

        $result = $resolver->resolve($dependency, $this->buildContainer($binding));

        $this->assertSame($instance, $result);
    }

    /**
     * - A union-typed dependency containing multiple resolvable class types with no
     *   default and no null throws a DependencyResolutionException because the
     *   resolver cannot determine which type to use.
     */
    #[Test]
    public function resolveUnionTypeWithMultipleClassTypesThrowsDependencyResolutionException(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithMultiClassUnionParam::class, 'dep');

        $this->expectException(DependencyResolutionException::class);

        $resolver->resolve($dependency, $this->buildContainer());
    }

    /**
     * - A union-typed dependency containing multiple resolvable class types that has
     *   a declared default returns that default rather than throwing.
     */
    #[Test]
    public function resolveUnionTypeWithMultipleClassTypesAndDefaultReturnsDefault(): void
    {
        $resolver   = new GenericResolver();
        $fallback   = new ClassWithMethods();
        $dependency = $this->dependencyFrom(ClassWithMultiClassUnionParam::class, 'dep', hasDefault: true, default: $fallback);

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertSame($fallback, $result);
    }

    /**
     * - A union-typed dependency containing multiple resolvable class types and null
     *   (ClassWithMethods|ClassWithProperty|null) returns null rather than throwing
     *   when no default is provided, because the union allows null.
     */
    #[Test]
    public function resolveUnionTypeWithNullableMultipleClassTypesReturnsNull(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithNullableMultiClassUnionParam::class, 'dep');

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertNull($result);
    }

    /**
     * - A union-typed dependency with a single resolvable interface type
     *   (AbstractInterface|string) where the interface cannot be instantiated causes
     *   the resolver to catch the failure and re-throw it as a
     *   DependencyResolutionException::union.
     */
    #[Test]
    public function resolveUnionTypeWithSingleResolvableTypeThatFailsThrowsUnionException(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithAbstractUnionParam::class, 'dep');

        $this->expectException(DependencyResolutionException::class);

        $resolver->resolve($dependency, $this->buildContainer());
    }

    // -------------------------------------------------------------------------
    // configureResolution — named and qualified
    // -------------------------------------------------------------------------

    /**
     * - When a dependency carries a Named attribute, configureResolution passes that
     *   name to the inner resolution so the container looks up the correct named binding.
     */
    #[Test]
    public function resolveWithNamedDependencyPassesNameToContainerResolution(): void
    {
        $resolver      = new GenericResolver();
        $namedInstance = new ClassWithMethods();
        $namedBinding  = new Binding(ClassWithMethods::class, instance: $namedInstance);
        $mainBinding   = new Binding(ClassWithMethods::class, namedMap: ['primary' => $namedBinding]);
        $dependency    = $this->dependencyFrom(ClassWithDependency::class, 'dependency', name: new Named('primary'));

        $result = $resolver->resolve($dependency, $this->buildContainer($mainBinding));

        $this->assertSame($namedInstance, $result);
    }

    /**
     * - When a dependency carries a qualifier, configureResolution passes that
     *   qualifier to the inner resolution so the container looks up the correct
     *   qualified binding.
     */
    #[Test]
    public function resolveWithQualifiedDependencyPassesQualifierToContainerResolution(): void
    {
        $resolver     = new GenericResolver();
        $qualifier    = new TestQualifier();
        $qualInstance = new ClassWithMethods();
        $qualBinding  = new Binding(ClassWithMethods::class, instance: $qualInstance);
        $mainBinding  = new Binding(ClassWithMethods::class, qualifiedMap: [TestQualifier::class => $qualBinding]);
        $dependency   = $this->dependencyFrom(ClassWithDependency::class, 'dependency', qualifier: $qualifier);

        $result = $resolver->resolve($dependency, $this->buildContainer($mainBinding));

        $this->assertSame($qualInstance, $result);
    }

    // -------------------------------------------------------------------------
    // resolveIntersectionType — binding variants
    // -------------------------------------------------------------------------

    /**
     * - An intersection-typed dependency where a binding maps to a concrete class
     *   resolves that concrete class and returns the instance if it satisfies the
     *   full intersection.
     */
    #[Test]
    public function resolveIntersectionTypeWithConcreteBindingResolvesConcreteClass(): void
    {
        $resolver   = new GenericResolver();
        $binding    = new Binding(Stringable::class, concrete: StringableCountable::class);
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $result = $resolver->resolve($dependency, $this->buildContainer($binding));

        $this->assertInstanceOf(StringableCountable::class, $result);
    }

    /**
     * - An intersection-typed dependency where a binding provides a factory callable
     *   invokes the factory and returns the instance if it satisfies the intersection.
     */
    #[Test]
    public function resolveIntersectionTypeWithFactoryBindingResolvesViaFactory(): void
    {
        $resolver   = new GenericResolver();
        $instance   = new StringableCountable();
        $binding    = new Binding(Stringable::class, factory: static fn () => $instance);
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $result = $resolver->resolve($dependency, $this->buildContainer($binding));

        $this->assertSame($instance, $result);
    }

    /**
     * - An intersection-typed dependency where a binding has no instance, concrete, or
     *   factory (a pass binding) is silently skipped, causing the resolver to fall
     *   through to the post-loop error path.
     */
    #[Test]
    public function resolveIntersectionTypeWithPassBindingThrowsDependencyResolutionException(): void
    {
        $resolver   = new GenericResolver();
        $binding    = new Binding(Stringable::class); // no instance, concrete, or factory
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $this->expectException(DependencyResolutionException::class);

        $resolver->resolve($dependency, $this->buildContainer($binding));
    }

    /**
     * - An intersection-typed dependency where a concrete binding points to a
     *   non-existent class causes an InvalidClassException, which is caught and
     *   skipped, causing the resolver to fall through to the post-loop error path.
     */
    #[Test]
    public function resolveIntersectionTypeIgnoresBindingWhenConcreteDoesNotExist(): void
    {
        $resolver = new GenericResolver();
        /** @var class-string $nonExistent */
        $nonExistent = 'NonExistentClass';
        $binding     = new Binding(Stringable::class, concrete: $nonExistent);
        $dependency  = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $this->expectException(DependencyResolutionException::class);

        $resolver->resolve($dependency, $this->buildContainer($binding));
    }

    /**
     * - When the first binding for an intersection type is a pass binding (no instance,
     *   concrete, or factory) and the second binding carries a valid instance that
     *   satisfies the full intersection, the resolver continues past the pass binding
     *   and returns the valid instance from the second binding.
     *
     * The fixture type is Countable&Stringable; Countable is the first type the
     * loop processes, so the pass binding must be registered under Countable to
     * ensure it is encountered before the valid Stringable binding.
     */
    #[Test]
    public function resolveIntersectionTypeSkipsPassBindingAndResolvesNextValidBinding(): void
    {
        $resolver   = new GenericResolver();
        $instance   = new StringableCountable();
        $pass       = new Binding(Countable::class); // pass binding — no instance/concrete/factory
        $valid      = new Binding(Stringable::class, instance: $instance);
        $dependency = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $result = $resolver->resolve($dependency, $this->buildContainer($pass, $valid));

        $this->assertSame($instance, $result);
    }

    /**
     * - When the first binding for an intersection type causes an InvalidClassException
     *   (e.g. a non-existent concrete class) and the second binding carries a valid
     *   instance that satisfies the full intersection, the resolver catches the exception,
     *   continues past the failing binding, and returns the valid instance.
     *
     * The fixture type is Countable&Stringable; Countable is the first type the
     * loop processes, so the bad binding must be registered under Countable to
     * ensure it is encountered before the valid Stringable binding.
     */
    #[Test]
    public function resolveIntersectionTypeSkipsInvalidClassBindingAndResolvesNextValidBinding(): void
    {
        $resolver = new GenericResolver();
        $instance = new StringableCountable();
        /** @var class-string $nonExistent */
        $nonExistent = 'NonExistentClass';
        $bad         = new Binding(Countable::class, concrete: $nonExistent);
        $valid       = new Binding(Stringable::class, instance: $instance);
        $dependency  = $this->dependencyFrom(ClassWithIntersectionParam::class, 'dep');

        $result = $resolver->resolve($dependency, $this->buildContainer($bad, $valid));

        $this->assertSame($instance, $result);
    }

    /**
     * - A nullable union-typed dependency (AbstractInterface|string|null) where the
     *   single resolvable class type fails to resolve must throw a
     *   DependencyResolutionException::union rather than silently returning null via
     *   the allowsNull fallback path.
     */
    #[Test]
    public function resolveUnionTypeWithSingleFailingTypeOnNullableUnionThrowsUnionException(): void
    {
        $resolver   = new GenericResolver();
        $dependency = $this->dependencyFrom(ClassWithNullableAbstractUnionParam::class, 'dep');

        $this->expectException(DependencyResolutionException::class);

        $resolver->resolve($dependency, $this->buildContainer());
    }
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildContainer(Binding ...$bindings): Container
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

    private function dependencyFrom(
        string $class,
        string $paramName,
        bool $hasDefault = false,
        mixed $default = null,
        bool $liminal = false,
        ?Named $name = null,
        ?Qualifier $qualifier = null,
    ): Dependency {
        $constructor = new ReflectionClass($class)->getConstructor();

        if ($constructor === null) {
            throw new RuntimeException("{$class} has no constructor");
        }

        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === $paramName) {
                /** @var ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|null $type */
                $type = $param->getType();

                return new Dependency(
                    $param->getName(),
                    $type,
                    hasDefault: $hasDefault,
                    default: $default,
                    liminal: $liminal,
                    name: $name,
                    qualifier: $qualifier,
                );
            }
        }

        throw new RuntimeException("Parameter '{$paramName}' not found on {$class}");
    }
}
