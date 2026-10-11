<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Resolvers;

use Engine\Container\Bindings\Binding;
use Engine\Container\Bindings\BindingCatalogue;
use Engine\Container\Container;
use Engine\Container\Dependency;
use Engine\Container\Exceptions\DependencyResolutionException;
use Engine\Container\Resolvers\GenericResolver;
use Engine\Container\Resolvers\GhostResolver;
use Engine\Container\Resolvers\ResolverCatalogue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;
use Tests\Unit\Container\Fixtures\AbstractInterface;
use Tests\Unit\Container\Fixtures\ClassWithGhostAbstractDependency;
use Tests\Unit\Container\Fixtures\ClassWithGhostDependency;
use Tests\Unit\Container\Fixtures\ClassWithProperty;
use Tests\Unit\Container\Fixtures\ClassWithRequiredScalarParam;
use Tests\Unit\Container\Fixtures\ConcreteClass;

#[Group('unit'), Group('container'), Group('ghost-resolver')]
class GhostResolverTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Successful ghost creation
    // -------------------------------------------------------------------------

    /**
     * - A dependency typed as a concrete class returns an uninitialized lazy ghost
     *   proxy rather than a real instance, deferring construction until the ghost
     *   is first accessed.
     */
    #[Test]
    public function resolveWithConcreteClassTypeReturnsUninitializedLazyGhost(): void
    {
        $resolver   = new GhostResolver();
        $dependency = $this->dependencyFrom(ClassWithGhostDependency::class, 'dependency');

        $ghost = $resolver->resolve($dependency, $this->buildContainer());

        $reflector = new ReflectionClass($ghost);
        $this->assertInstanceOf(ClassWithProperty::class, $ghost);
        $this->assertTrue($reflector->isUninitializedLazyObject($ghost));
    }

    /**
     * - Accessing a property on the ghost object triggers the lazy initializer,
     *   which invokes the class constructor so properties are set to their
     *   expected constructed values.
     */
    #[Test]
    public function resolveWithConcreteClassGhostInvokesConstructorOnFirstAccess(): void
    {
        $resolver   = new GhostResolver();
        $dependency = $this->dependencyFrom(ClassWithGhostDependency::class, 'dependency');

        /** @var ClassWithProperty $ghost */
        $ghost = $resolver->resolve($dependency, $this->buildContainer());

        // Accessing the property triggers the lazy initializer, which calls __construct.
        $this->assertSame('initialized', $ghost->value);
    }

    /**
     * - A dependency typed as an interface where a binding maps to a concrete class
     *   creates the ghost proxy for the concrete class rather than attempting to
     *   instantiate the interface directly.
     */
    #[Test]
    public function resolveWithBindingToConcreteCreatesGhostForConcreteClass(): void
    {
        $resolver   = new GhostResolver();
        $binding    = new Binding(AbstractInterface::class, concrete: ConcreteClass::class);
        $dependency = $this->dependencyFrom(ClassWithGhostAbstractDependency::class, 'dep');

        $ghost = $resolver->resolve($dependency, $this->buildContainer($binding));

        $this->assertInstanceOf(ConcreteClass::class, $ghost);
    }

    /**
     * - A ghost for a class that has no explicit constructor can still be initialized
     *   without error, since the resolver skips the constructor invocation step when
     *   the class does not define __construct.
     */
    #[Test]
    public function resolveWithClassWithoutConstructorInitializesGhostWithoutError(): void
    {
        $resolver   = new GhostResolver();
        $binding    = new Binding(AbstractInterface::class, concrete: ConcreteClass::class);
        $dependency = $this->dependencyFrom(ClassWithGhostAbstractDependency::class, 'dep');

        $ghost = $resolver->resolve($dependency, $this->buildContainer($binding));

        // Explicitly trigger initialization to exercise the no-constructor branch.
        $reflector = new ReflectionClass($ghost);
        $reflector->initializeLazyObject($ghost);

        $this->assertFalse($reflector->isUninitializedLazyObject($ghost));
    }

    // -------------------------------------------------------------------------
    // Error paths
    // -------------------------------------------------------------------------

    /**
     * - A dependency with no type at all (null) cannot produce a ghost object,
     *   so the resolver throws a DependencyResolutionException with 'null' in the
     *   message indicating no type was present.
     */
    #[Test]
    public function resolveWithNullTypeThrowsDependencyResolutionExceptionWithNullMessage(): void
    {
        $resolver   = new GhostResolver();
        $dependency = new Dependency('param', null);

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage('"null"');

        $resolver->resolve($dependency, $this->buildContainer());
    }

    /**
     * - A dependency typed as a built-in scalar (string) cannot produce a ghost
     *   object because scalars are not classes, so the resolver throws a
     *   DependencyResolutionException naming the offending type.
     */
    #[Test]
    public function resolveWithScalarTypeThrowsDependencyResolutionExceptionWithTypeName(): void
    {
        $resolver   = new GhostResolver();
        $dependency = $this->dependencyFrom(ClassWithRequiredScalarParam::class, 'value');

        $this->expectException(DependencyResolutionException::class);
        $this->expectExceptionMessage('"string"');

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

    private function dependencyFrom(string $class, string $paramName): Dependency
    {
        $constructor = new ReflectionClass($class)->getConstructor();

        if ($constructor === null) {
            throw new RuntimeException("{$class} has no constructor");
        }

        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === $paramName) {
                /** @var ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|null $type */
                $type = $param->getType();

                return new Dependency($param->getName(), $type);
            }
        }

        throw new RuntimeException("Parameter '{$paramName}' not found on {$class}");
    }
}
