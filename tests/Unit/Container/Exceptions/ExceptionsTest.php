<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Exceptions;

use Engine\Container\Attributes\NoResolution;
use Engine\Container\Contracts\ContainerException;
use Engine\Container\Exceptions\BindingNotFoundException;
use Engine\Container\Exceptions\DependencyResolutionException;
use Engine\Container\Exceptions\InvalidAttributeException;
use Engine\Container\Exceptions\InvalidClassException;
use Engine\Container\Exceptions\InvalidFunctionException;
use Engine\Container\Exceptions\InvalidInvocationException;
use Engine\Container\Exceptions\InvalidMethodException;
use Engine\Container\Exceptions\InvalidResolutionException;
use Engine\Container\Exceptions\InvalidResolverException;
use Engine\Container\Exceptions\MethodCallException;
use Engine\Container\Exceptions\NotInstantiableException;
use Engine\Container\Exceptions\UnresolvableClassException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('unit'), Group('container'), Group('exceptions')]
class ExceptionsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // InvalidAttributeException
    // -------------------------------------------------------------------------

    /**
     * - An attribute that is not a tracked marker produces a message naming it, and the
     *   exception is an invalid argument that the container contract covers.
     */
    #[Test]
    public function invalidAttributeNotMarkerProducesExpectedMessage(): void
    {
        $e = InvalidAttributeException::notMarker('SomeAttribute');

        $this->assertInstanceOf(InvalidAttributeException::class, $e);
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
        $this->assertInstanceOf(ContainerException::class, $e);
        $this->assertSame(
            'The attribute "SomeAttribute" is not a class-level marker the container tracks',
            $e->getMessage(),
        );
    }

    // -------------------------------------------------------------------------
    // InvalidResolutionException
    // -------------------------------------------------------------------------

    /**
     * - A class identified by both a name and a qualifier produces a message naming all
     *   three, and the exception is an invalid argument that the container contract
     *   covers.
     */
    #[Test]
    public function invalidResolutionForDoubleIdentifiedClassProducesExpectedMessage(): void
    {
        $e = InvalidResolutionException::doubleIdentifiedClass('SomeClass', 'primary', 'SomeQualifier');

        $this->assertInstanceOf(InvalidResolutionException::class, $e);
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
        $this->assertInstanceOf(ContainerException::class, $e);
        $this->assertSame(
            'Class "SomeClass" cannot have both a name "primary", and qualifier "SomeQualifier".',
            $e->getMessage(),
        );
    }

    // -------------------------------------------------------------------------
    // BindingNotFoundException
    // -------------------------------------------------------------------------

    /**
     * - A binding lookup failure for a plain class produces the expected message.
     */
    #[Test]
    public function bindingNotFoundForClassProducesExpectedMessage(): void
    {
        $e = BindingNotFoundException::class('SomeClass');

        $this->assertInstanceOf(BindingNotFoundException::class, $e);
        $this->assertSame('No binding found for SomeClass', $e->getMessage());
    }

    /**
     * - A named binding lookup failure produces the expected message.
     */
    #[Test]
    public function bindingNotFoundForNamedProducesExpectedMessage(): void
    {
        $e = BindingNotFoundException::named('SomeClass', 'primary');

        $this->assertInstanceOf(BindingNotFoundException::class, $e);
        $this->assertSame('No binding found for SomeClass with name primary', $e->getMessage());
    }

    /**
     * - A qualified binding lookup failure produces the expected message.
     */
    #[Test]
    public function bindingNotFoundForQualifiedProducesExpectedMessage(): void
    {
        $e = BindingNotFoundException::qualified('SomeClass', 'SomeQualifier');

        $this->assertInstanceOf(BindingNotFoundException::class, $e);
        $this->assertSame('No binding found for SomeClass qualified by SomeQualifier', $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // InvalidClassException
    // -------------------------------------------------------------------------

    /**
     * - An invalid class error produces the expected message.
     */
    #[Test]
    public function invalidClassMakeProducesExpectedMessage(): void
    {
        $e = InvalidClassException::make('NonExistentClass');

        $this->assertInstanceOf(InvalidClassException::class, $e);
        $this->assertSame('The provided class NonExistentClass is not a valid class.', $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // InvalidFunctionException
    // -------------------------------------------------------------------------

    /**
     * - An invalid function error produces the expected message.
     */
    #[Test]
    public function invalidFunctionMakeProducesExpectedMessage(): void
    {
        $e = InvalidFunctionException::make('nonExistentFunction');

        $this->assertInstanceOf(InvalidFunctionException::class, $e);
        $this->assertSame('The provided function nonExistentFunction does not exist.', $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // InvalidMethodException
    // -------------------------------------------------------------------------

    /**
     * - An invalid method error produces the expected message.
     */
    #[Test]
    public function invalidMethodMakeProducesExpectedMessage(): void
    {
        $e = InvalidMethodException::make('SomeClass', 'badMethod');

        $this->assertInstanceOf(InvalidMethodException::class, $e);
        $this->assertSame('The provided method SomeClass::badMethod is not a valid method.', $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // MethodCallException
    // -------------------------------------------------------------------------

    /**
     * - A method call failure error produces the expected message.
     */
    #[Test]
    public function methodCallExceptionMakeProducesExpectedMessage(): void
    {
        $e = MethodCallException::make('SomeClass', 'someMethod');

        $this->assertInstanceOf(MethodCallException::class, $e);
        $this->assertSame('Unable to call the provided method SomeClass::someMethod.', $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // NotInstantiableException
    // -------------------------------------------------------------------------

    /**
     * - A not-instantiable error produces the expected message.
     */
    #[Test]
    public function notInstantiableMakeProducesExpectedMessage(): void
    {
        $e = NotInstantiableException::make('AbstractThing');

        $this->assertInstanceOf(NotInstantiableException::class, $e);
        $this->assertSame('Class AbstractThing is not instantiable', $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // UnresolvableClassException
    // -------------------------------------------------------------------------

    /**
     * - An unresolvable class error produces the expected message, naming both
     *   the class and the NoResolution attribute.
     */
    #[Test]
    public function unresolvableClassMakeProducesExpectedMessage(): void
    {
        $e = UnresolvableClassException::make('LockedClass');

        $this->assertInstanceOf(UnresolvableClassException::class, $e);
        $this->assertSame(
            sprintf(
                'The class LockedClass is marked with \'%s\', so cannot be resolved automatically.',
                NoResolution::class,
            ),
            $e->getMessage(),
        );
    }

    // -------------------------------------------------------------------------
    // DependencyResolutionException
    // -------------------------------------------------------------------------

    /**
     * - A cannot-resolve error produces the expected message.
     */
    #[Test]
    public function dependencyCannotResolveProducesExpectedMessage(): void
    {
        $e = DependencyResolutionException::cannotResolve('string');

        $this->assertInstanceOf(DependencyResolutionException::class, $e);
        $this->assertSame('Cannot resolve a dependency of type "string".', $e->getMessage());
    }

    /**
     * - An intersection resolution failure error produces the expected message.
     */
    #[Test]
    public function dependencyIntersectionProducesExpectedMessage(): void
    {
        $e = DependencyResolutionException::intersection('FooInterface&BarInterface');

        $this->assertInstanceOf(DependencyResolutionException::class, $e);
        $this->assertSame('Cannot resolve the intersection dependency "FooInterface&BarInterface".', $e->getMessage());
    }

    /**
     * - An intersection-no-binding error produces the expected message.
     */
    #[Test]
    public function dependencyIntersectionNoBindingProducesExpectedMessage(): void
    {
        $e = DependencyResolutionException::intersectionNoBinding('FooInterface&BarInterface');

        $this->assertInstanceOf(DependencyResolutionException::class, $e);
        $this->assertSame('Cannot resolve the intersection dependency "FooInterface&BarInterface" without a binding.', $e->getMessage());
    }

    /**
     * - A union resolution failure error produces the expected message.
     */
    #[Test]
    public function dependencyUnionProducesExpectedMessage(): void
    {
        $e = DependencyResolutionException::union('Foo|Bar');

        $this->assertInstanceOf(DependencyResolutionException::class, $e);
        $this->assertSame('Cannot resolve the union dependency "Foo|Bar".', $e->getMessage());
    }

    /**
     * - A ghost resolution failure error produces the expected message.
     */
    #[Test]
    public function dependencyGhostProducesExpectedMessage(): void
    {
        $e = DependencyResolutionException::ghost('string');

        $this->assertInstanceOf(DependencyResolutionException::class, $e);
        $this->assertSame('Cannot create a ghost object for "string".', $e->getMessage());
    }

    /**
     * - A named-and-qualified error produces the expected message.
     */
    #[Test]
    public function dependencyNamedAndQualifiedProducesExpectedMessage(): void
    {
        $e = DependencyResolutionException::namedAndQualified();

        $this->assertInstanceOf(DependencyResolutionException::class, $e);
        $this->assertSame('Cannot resolve a dependency using both a name and a qualifier.', $e->getMessage());
    }

    /**
     * - A parameter resolution failure names the parameter and its function, and carries
     *   the failure it wraps as the previous exception.
     */
    #[Test]
    public function dependencyParameterProducesExpectedMessageAndPrevious(): void
    {
        $previous = new RuntimeException('inner');
        $e        = DependencyResolutionException::parameter('dep', 'SomeClass::__construct', $previous);

        $this->assertInstanceOf(DependencyResolutionException::class, $e);
        $this->assertSame('Cannot resolve the parameter "$dep" of "SomeClass::__construct".', $e->getMessage());
        $this->assertSame($previous, $e->getPrevious());
    }

    // -------------------------------------------------------------------------
    // InvalidResolverException
    // -------------------------------------------------------------------------

    /**
     * - An unregistered resolvable error produces the expected message.
     */
    #[Test]
    public function invalidResolverUnregisteredProducesExpectedMessage(): void
    {
        $e = InvalidResolverException::unregistered('SomeResolvable');

        $this->assertInstanceOf(InvalidResolverException::class, $e);
        $this->assertSame('"SomeResolvable" is not a registered resolvable.', $e->getMessage());
    }

    /**
     * - An invalid-resolvable error produces the expected message.
     */
    #[Test]
    public function invalidResolverResolvableProducesExpectedMessage(): void
    {
        $e = InvalidResolverException::resolvable('NotAResolvable');

        $this->assertInstanceOf(InvalidResolverException::class, $e);
        $this->assertSame('"NotAResolvable" is not a valid resolvable.', $e->getMessage());
    }

    /**
     * - An invalid-resolver error produces the expected message.
     */
    #[Test]
    public function invalidResolverResolverProducesExpectedMessage(): void
    {
        $e = InvalidResolverException::resolver('NotAResolver');

        $this->assertInstanceOf(InvalidResolverException::class, $e);
        $this->assertSame('"NotAResolver" is not a valid resolver.', $e->getMessage());
    }

    /**
     * - A no-default-resolver error produces the expected message.
     */
    #[Test]
    public function invalidResolverNoDefaultProducesExpectedMessage(): void
    {
        $e = InvalidResolverException::noDefault();

        $this->assertInstanceOf(InvalidResolverException::class, $e);
        $this->assertSame('There is no default resolver.', $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // InvalidInvocationException
    // -------------------------------------------------------------------------

    /**
     * - A not-public invocation error produces the expected message.
     */
    #[Test]
    public function invalidInvocationNotPublicProducesExpectedMessage(): void
    {
        $e = InvalidInvocationException::notPublic('SomeClass', 'privateMethod');

        $this->assertInstanceOf(InvalidInvocationException::class, $e);
        $this->assertSame('Method SomeClass::privateMethod is not public.', $e->getMessage());
    }

    /**
     * - An is-static invocation error produces the expected message.
     */
    #[Test]
    public function invalidInvocationIsStaticProducesExpectedMessage(): void
    {
        $e = InvalidInvocationException::isStatic('SomeClass', 'staticMethod');

        $this->assertInstanceOf(InvalidInvocationException::class, $e);
        $this->assertSame(
            'Method SomeClass::staticMethod is static and cannot be invoked on an object instance.',
            $e->getMessage(),
        );
    }

    /**
     * - A not-callable error produces the expected message.
     */
    #[Test]
    public function invalidInvocationNotCallableProducesExpectedMessage(): void
    {
        $e = InvalidInvocationException::notCallable();

        $this->assertInstanceOf(InvalidInvocationException::class, $e);
        $this->assertSame('Cannot invoke a non-callable.', $e->getMessage());
    }

    /**
     * - A not-method error produces the expected message.
     */
    #[Test]
    public function invalidInvocationNotMethodProducesExpectedMessage(): void
    {
        $e = InvalidInvocationException::notMethod();

        $this->assertInstanceOf(InvalidInvocationException::class, $e);
        $this->assertSame('Cannot invoke a non-string method.', $e->getMessage());
    }
}
