<?php
declare(strict_types=1);

namespace Tests\Unit\Container;

use Engine\Container\Attributes\Lazy;
use Engine\Container\Contracts\Resolvable;
use Engine\Container\Dependency;
use Engine\Container\Exceptions\InvalidClassException;
use Engine\Container\Exceptions\InvalidMethodException;
use Engine\Container\ReflectionHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Tests\Unit\Container\Fixtures\ClassWithMethods;
use Tests\Unit\Container\Fixtures\ClassWithProperty;
use Tests\Unit\Container\Fixtures\ClassWithTypedParameters;
use Tests\Unit\Container\Fixtures\InvokableClass;
use Tests\Unit\Container\Fixtures\LazyClass;

#[Group('unit'), Group('container'), Group('reflection-helper')]
class ReflectionHelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // isSingleType
    // -------------------------------------------------------------------------

    /**
     * - A constructor parameter declared with a single class type (e.g. ClassWithMethods)
     *   is correctly identified as a named type, not a union or intersection.
     */
    #[Test]
    public function isSingleTypeReturnsTrueForNamedTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'namedType');

        $this->assertTrue(ReflectionHelper::isSingleType($dependency));
    }

    /**
     * - A constructor parameter declared as a union type (string|int) is not
     *   a single named type, so isSingleType returns false.
     */
    #[Test]
    public function isSingleTypeReturnsFalseForUnionTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'unionType');

        $this->assertFalse(ReflectionHelper::isSingleType($dependency));
    }

    // -------------------------------------------------------------------------
    // isIntersectionType
    // -------------------------------------------------------------------------

    /**
     * - A constructor parameter declared with an intersection type (Stringable&Countable)
     *   is correctly identified as an intersection type.
     */
    #[Test]
    public function isIntersectionTypeReturnsTrueForIntersectionTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'intersectionType');

        $this->assertTrue(ReflectionHelper::isIntersectionType($dependency));
    }

    /**
     * - A constructor parameter declared with a single named type is not an
     *   intersection type, so isIntersectionType returns false.
     */
    #[Test]
    public function isIntersectionTypeReturnsFalseForNamedTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'namedType');

        $this->assertFalse(ReflectionHelper::isIntersectionType($dependency));
    }

    // -------------------------------------------------------------------------
    // isUnionType
    // -------------------------------------------------------------------------

    /**
     * - A constructor parameter declared as a union type (string|int) is correctly
     *   identified as a union type.
     */
    #[Test]
    public function isUnionTypeReturnsTrueForUnionTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'unionType');

        $this->assertTrue(ReflectionHelper::isUnionType($dependency));
    }

    /**
     * - A constructor parameter declared with a single named type is not a union
     *   type, so isUnionType returns false.
     */
    #[Test]
    public function isUnionTypeReturnsFalseForNamedTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'namedType');

        $this->assertFalse(ReflectionHelper::isUnionType($dependency));
    }

    // -------------------------------------------------------------------------
    // getTypeClassName
    // -------------------------------------------------------------------------

    /**
     * - A dependency with a single named type returns the fully-qualified class
     *   name so the container can look it up in the binding catalogue.
     */
    #[Test]
    public function getTypeClassNameReturnsNameForNamedTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'namedType');

        $this->assertSame(ClassWithMethods::class, ReflectionHelper::getTypeClassName($dependency));
    }

    /**
     * - A dependency with a union type cannot be expressed as a single class name,
     *   so getTypeClassName returns null.
     */
    #[Test]
    public function getTypeClassNameReturnsNullForUnionTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'unionType');

        $this->assertNull(ReflectionHelper::getTypeClassName($dependency));
    }

    /**
     * - A dependency with an intersection type cannot be expressed as a single class
     *   name, so getTypeClassName returns null.
     */
    #[Test]
    public function getTypeClassNameReturnsNullForIntersectionTypeDependency(): void
    {
        $dependency = $this->dependencyFrom(ClassWithTypedParameters::class, 'intersectionType');

        $this->assertNull(ReflectionHelper::getTypeClassName($dependency));
    }

    // -------------------------------------------------------------------------
    // getMethodReflector
    // -------------------------------------------------------------------------

    /**
     * - Passing a class name string and method name returns a ReflectionMethod
     *   for that exact method, confirming the string branch works.
     */
    #[Test]
    public function getMethodReflectorReturnsReflectionMethodForClassNameString(): void
    {
        $method = ReflectionHelper::getMethodReflector(ClassWithMethods::class, 'callableMethod');

        $this->assertInstanceOf(ReflectionMethod::class, $method);
        $this->assertSame('callableMethod', $method->getName());
    }

    /**
     * - Passing an object instance and method name returns a ReflectionMethod,
     *   confirming the object branch resolves the class name automatically.
     */
    #[Test]
    public function getMethodReflectorReturnsReflectionMethodForObjectInstance(): void
    {
        $method = ReflectionHelper::getMethodReflector(new ClassWithMethods(), 'callableMethod');

        $this->assertInstanceOf(ReflectionMethod::class, $method);
        $this->assertSame('callableMethod', $method->getName());
    }

    /**
     * - Passing an existing ReflectionClass and method name delegates to
     *   ReflectionClass::getMethod, returning the correct ReflectionMethod.
     */
    #[Test]
    public function getMethodReflectorReturnsReflectionMethodForReflectionClass(): void
    {
        $reflector = new ReflectionClass(ClassWithMethods::class);
        $method    = ReflectionHelper::getMethodReflector($reflector, 'callableMethod');

        $this->assertInstanceOf(ReflectionMethod::class, $method);
        $this->assertSame('callableMethod', $method->getName());
    }

    /**
     * - A method name that does not exist on the class throws an
     *   InvalidMethodException rather than surfacing a raw ReflectionException.
     */
    #[Test]
    public function getMethodReflectorThrowsInvalidMethodExceptionForNonExistentMethod(): void
    {
        $this->expectException(InvalidMethodException::class);

        ReflectionHelper::getMethodReflector(ClassWithMethods::class, 'nonExistentMethod');
    }

    // -------------------------------------------------------------------------
    // getFunctionReflector
    // -------------------------------------------------------------------------

    /**
     * - Passing a Closure returns a ReflectionFunction that reflects that closure,
     *   confirming the function reflector works for anonymous functions.
     */
    #[Test]
    public function getFunctionReflectorReturnsReflectionFunctionForClosure(): void
    {
        $closure   = static fn (): bool => true;
        $reflector = ReflectionHelper::getFunctionReflector($closure);

        $this->assertInstanceOf(ReflectionFunction::class, $reflector);
    }

    /**
     * - Passing a named global function string returns a ReflectionFunction that
     *   reflects that function.
     */
    #[Test]
    public function getFunctionReflectorReturnsReflectionFunctionForNamedFunction(): void
    {
        $reflector = ReflectionHelper::getFunctionReflector('strlen');

        $this->assertInstanceOf(ReflectionFunction::class, $reflector);
        $this->assertSame('strlen', $reflector->getName());
    }

    // -------------------------------------------------------------------------
    // getFunctionName
    // -------------------------------------------------------------------------

    /**
     * - A plain string function name is returned as-is, since it already uniquely
     *   identifies the function.
     */
    #[Test]
    public function getFunctionNameReturnsStringForStringCallable(): void
    {
        $this->assertSame('strlen', ReflectionHelper::getFunctionName('strlen'));
    }

    /**
     * - A Closure produces a name in the form \Closure{hash}, allowing callers to
     *   include it in error messages without crashing on non-string callables.
     */
    #[Test]
    public function getFunctionNameReturnsCurlyHashedNameForClosure(): void
    {
        $closure = static fn () => true;
        $name    = ReflectionHelper::getFunctionName($closure);

        $this->assertSame('\Closure{' . spl_object_hash($closure) . '}', $name);
    }

    /**
     * - An invokable object produces a name in the form ClassName::__invoke,
     *   making it clear which class and magic method are being called.
     */
    #[Test]
    public function getFunctionNameReturnsClassInvokeForInvokableObject(): void
    {
        $name = ReflectionHelper::getFunctionName(new InvokableClass());

        $this->assertSame(InvokableClass::class . '::__invoke', $name);
    }

    /**
     * - An array callable with an object instance produces ClassName::method,
     *   correctly deriving the class name from the object rather than using a
     *   string literal.
     */
    #[Test]
    public function getFunctionNameReturnsClassMethodForArrayCallableWithObject(): void
    {
        $name = ReflectionHelper::getFunctionName([new ClassWithMethods(), 'callableMethod']);

        $this->assertSame(ClassWithMethods::class . '::callableMethod', $name);
    }

    /**
     * - An array callable with a class name string produces ClassName::method,
     *   confirming the static-method array form is handled correctly.
     */
    #[Test]
    public function getFunctionNameReturnsClassMethodForArrayCallableWithClassString(): void
    {
        $name = ReflectionHelper::getFunctionName([ClassWithMethods::class, 'callableStaticMethod']);

        $this->assertSame(ClassWithMethods::class . '::callableStaticMethod', $name);
    }

    // -------------------------------------------------------------------------
    // getLazyProxy
    // -------------------------------------------------------------------------

    /**
     * - A class with a typed property produces a genuine uninitialized lazy proxy,
     *   confirming that the factory callable is not invoked until the proxy is
     *   first accessed.
     */
    #[Test]
    public function getLazyProxyReturnsUninitializedProxyForClassWithTypedProperty(): void
    {
        $proxy = ReflectionHelper::getLazyProxy(ClassWithProperty::class, fn () => new ClassWithProperty());

        $reflector = new ReflectionClass($proxy);
        $this->assertTrue($reflector->isUninitializedLazyObject($proxy));
    }

    // -------------------------------------------------------------------------
    // getClassReflector
    // -------------------------------------------------------------------------

    /**
     * - A valid class name returns a ReflectionClass instance for exactly that class.
     */
    #[Test]
    public function getClassReflectorReturnsReflectionClassForValidClass(): void
    {
        $reflector = ReflectionHelper::getClassReflector(ClassWithMethods::class);

        $this->assertInstanceOf(ReflectionClass::class, $reflector);
        $this->assertSame(ClassWithMethods::class, $reflector->getName());
    }

    /**
     * - A class name that does not exist throws an InvalidClassException rather than
     *   surfacing a raw ReflectionException to the caller.
     */
    #[Test]
    public function getClassReflectorThrowsForNonExistentClass(): void
    {
        $this->expectException(InvalidClassException::class);

        ReflectionHelper::getClassReflector('NonExistentClass');
    }

    /**
     * - The default `$instanceOf = false` performs an exact class match, so looking
     *   up a parent interface returns null even when an implementing attribute is present.
     */
    #[Test]
    public function getAttributeInstanceReturnsNullForParentTypeWithDefaultExactMatch(): void
    {
        // LazyClass has #[Lazy], and Lazy implements Resolvable. With exact matching
        // (the default), searching for Resolvable::class finds nothing.
        $reflector = new ReflectionClass(LazyClass::class);

        $result = ReflectionHelper::getAttributeInstance($reflector, Resolvable::class);

        $this->assertNull($result);
    }

    /**
     * - Passing `$instanceOf = true` uses `IS_INSTANCEOF` flag matching, so a search
     *   for a parent interface finds attributes whose class implements that interface.
     */
    #[Test]
    public function getAttributeInstanceWithInstanceOfFindsDerivedAttributeViaParentType(): void
    {
        // LazyClass has #[Lazy]. Lazy implements Resolvable. With $instanceOf = true,
        // searching for Resolvable::class returns the Lazy attribute instance.
        $reflector = new ReflectionClass(LazyClass::class);

        $result = ReflectionHelper::getAttributeInstance($reflector, Resolvable::class, true);

        $this->assertInstanceOf(Lazy::class, $result);
    }

    /**
     * - When a class has a single attribute, the first (index 0) element is returned,
     *   confirming the correct array index is used.
     */
    #[Test]
    public function getAttributeInstanceReturnsFirstAttributeForClassWithSingleAttribute(): void
    {
        // LazyClass has exactly one attribute (#[Lazy]). Index [0] returns it;
        // index [1] would return null.
        $reflector = new ReflectionClass(LazyClass::class);

        $result = ReflectionHelper::getAttributeInstance($reflector, Lazy::class);

        $this->assertInstanceOf(Lazy::class, $result);
    }
    // -------------------------------------------------------------------------
    // getFunctionNameFromReflection
    // -------------------------------------------------------------------------

    /**
     * - A reflected method is named by its class and method.
     */
    #[Test]
    public function getFunctionNameFromReflectionReturnsClassAndMethodForMethod(): void
    {
        $name = ReflectionHelper::getFunctionNameFromReflection(new ReflectionMethod(InvokableClass::class, '__invoke'));

        $this->assertSame(InvokableClass::class . '::__invoke', $name);
    }

    /**
     * - A reflected function is named by its function name alone.
     */
    #[Test]
    public function getFunctionNameFromReflectionReturnsNameForFunction(): void
    {
        $name = ReflectionHelper::getFunctionNameFromReflection(new ReflectionFunction('strlen'));

        $this->assertSame('strlen', $name);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function dependencyFrom(string $class, string $parameter): Dependency
    {
        $constructor = new ReflectionClass($class)->getConstructor();

        if ($constructor === null) {
            throw new \RuntimeException("{$class} has no constructor");
        }

        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === $parameter) {
                /** @var \ReflectionNamedType|\ReflectionUnionType|\ReflectionIntersectionType|null $type */
                $type = $param->getType();

                return new Dependency($param->getName(), $type);
            }
        }

        throw new \RuntimeException("Parameter '{$parameter}' not found on {$class}");
    }
}
