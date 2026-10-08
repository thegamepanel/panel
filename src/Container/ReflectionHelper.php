<?php
declare(strict_types=1);

namespace Engine\Container;

use Closure;
use Engine\Container\Exceptions\InvalidClassException;
use Engine\Container\Exceptions\InvalidFunctionException;
use Engine\Container\Exceptions\InvalidMethodException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

final readonly class ReflectionHelper
{
    /**
     * @param \Engine\Container\Dependency<*, *, *> $dependency
     *
     * @return bool
     *
     * @phpstan-assert-if-true ReflectionNamedType $dependency->type
     */
    public static function isSingleType(Dependency $dependency): bool
    {
        return $dependency->type instanceof ReflectionNamedType;
    }

    /**
     * @param \Engine\Container\Dependency<*, *, *> $dependency
     *
     * @return bool
     *
     * @phpstan-assert-if-true ReflectionIntersectionType $dependency->type
     */
    public static function isIntersectionType(Dependency $dependency): bool
    {
        return $dependency->type instanceof ReflectionIntersectionType;
    }

    /**
     * @param \Engine\Container\Dependency<*, *, *> $dependency
     *
     * @return bool
     *
     * @phpstan-assert-if-true \ReflectionUnionType $dependency->type
     */
    public static function isUnionType(Dependency $dependency): bool
    {
        return $dependency->type instanceof ReflectionUnionType;
    }

    /**
     * @param \Engine\Container\Dependency<*, *, *> $dependency
     *
     * @return string|null
     */
    public static function getTypeClassName(Dependency $dependency): ?string
    {
        if (self::isSingleType($dependency)) {
            /** @var ReflectionNamedType $type */
            $type = $dependency->type;

            return $type->getName();
        }

        return null;
    }

    /**
     * Get the reflection class for the given class/object.
     *
     * @template TClass of object
     *
     * @param class-string<TClass>|TClass $class
     *
     * @return ReflectionClass<TClass>
     *
     * @throws InvalidClassException
     *
     * @phpstan-ignore throws.unusedType
     */
    public static function getClassReflector(object|string $class): ReflectionClass
    {
        try {
            return new ReflectionClass($class);
            /** @phpstan-ignore catch.neverThrown */
        } catch (ReflectionException $e) {
            throw InvalidClassException::make(
                is_object($class) ? $class::class : $class,
                $e,
            );
        }
    }

    /**
     * Get the reflection method for the given class/object and method.
     *
     * @param class-string|object $class
     * @param string              $method
     *
     * @return ReflectionMethod
     *
     * @throws InvalidMethodException
     */
    public static function getMethodReflector(object|string $class, string $method): ReflectionMethod
    {
        try {
            if ($class instanceof ReflectionClass) {
                $className = $class->getName();

                return $class->getMethod($method);
            }

            $className = is_object($class) ? $class::class : $class;

            return new ReflectionMethod($class, $method);
        } catch (ReflectionException $e) {
            throw InvalidMethodException::make(
                $className,
                $method,
                $e,
            );
        }
    }

    /**
     * Get the reflection function for the given function.
     *
     * @param callable $function
     *
     * @return ReflectionFunction
     */
    public static function getFunctionReflector(callable $function): ReflectionFunction
    {
        try {
            return new ReflectionFunction($function(...));
        } catch (ReflectionException $e) { // @codeCoverageIgnoreStart
            // Unreachable — the spread operator ($function(...)) always produces
            // a valid Closure, so ReflectionFunction cannot fail here.
            throw InvalidFunctionException::make(
                self::getFunctionName($function),
                $e,
            );
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Get the name of the function from the given callable.
     *
     * @param callable $function
     *
     * @return string
     */
    public static function getFunctionName(callable $function): string
    {
        if (is_string($function)) {
            return $function;
        }

        if ($function instanceof Closure) {
            return '\Closure{' . spl_object_hash($function) . '}';
        }

        if (is_object($function)) {
            return $function::class . '::__invoke';
        }

        if (is_array($function)) {
            /** @var array{0: class-string|object, 1: string}&callable $function */
            if (is_object($function[0])) {
                return $function[0]::class . '::' . $function[1];
            }

            return $function[0] . '::' . $function[1];
        }

        // Unreachable — all callable forms are handled above (string, Closure, invokable object, array).
        return 'function'; // @codeCoverageIgnore
    }

    /**
     * Get the name of the function from the given reflection.
     *
     * @param ReflectionFunctionAbstract $reflector
     *
     * @return string
     */
    public static function getFunctionNameFromReflection(ReflectionFunctionAbstract $reflector): string
    {
        return $reflector instanceof ReflectionMethod
            ? $reflector->class . '::' . $reflector->getName()
            : $reflector->getName();
    }

    /**
     * @template TAttribute of object
     *
     * @param ReflectionClass<*>|ReflectionMethod|ReflectionFunction|ReflectionParameter $reflector
     * @param class-string<TAttribute> $class
     * @param bool                     $instanceOf
     *
     * @return object|null
     *
     * @phpstan-return TAttribute|null
     */
    public static function getAttributeInstance(
        ReflectionClass|ReflectionFunction|ReflectionMethod|ReflectionParameter $reflector,
        string                                                                  $class,
        bool                                                                    $instanceOf = false,
    ): ?object {
        $attribute = $reflector->getAttributes($class, $instanceOf ? ReflectionAttribute::IS_INSTANCEOF : 0)[0] ?? null;

        /** @var TAttribute|null $instance */
        $instance = $attribute?->newInstance();

        return $instance;
    }

    /**
     * @template TLazyClass of object
     *
     * @param class-string<TLazyClass> $class
     * @param callable():TLazyClass    $factory
     *
     * @return TLazyClass
     */
    public static function getLazyProxy(string $class, callable $factory): object
    {
        /** @var TLazyClass */
        return self::getClassReflector($class)->newLazyProxy($factory);
    }
}
