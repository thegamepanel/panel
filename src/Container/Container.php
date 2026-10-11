<?php
declare(strict_types=1);

namespace Engine\Container;

use Engine\Container\Attributes\Lazy;
use Engine\Container\Attributes\Liminal;
use Engine\Container\Attributes\Named;
use Engine\Container\Attributes\NoResolution;
use Engine\Container\Bindings\Binding;
use Engine\Container\Bindings\BindingCatalogue;
use Engine\Container\Contracts\Qualifier;
use Engine\Container\Contracts\Resolvable;
use Engine\Container\Contracts\Resolver;
use Engine\Container\Exceptions\BindingNotFoundException;
use Engine\Container\Exceptions\CircularDependencyException;
use Engine\Container\Exceptions\DependencyResolutionException;
use Engine\Container\Exceptions\InvalidInvocationException;
use Engine\Container\Exceptions\MethodCallException;
use Engine\Container\Exceptions\NotInstantiableException;
use Engine\Container\Exceptions\UnresolvableClassException;
use Engine\Container\Resolvers\ResolverCatalogue;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;

final class Container
{
    private(set) BindingCatalogue $bindings;

    /**
     * @var ResolverCatalogue
     */
    private ResolverCatalogue $resolvers;

    /**
     * @var InstanceCache<false>
     */
    private InstanceCache $instances;

    /**
     * @var InstanceCache<true>
     */
    private InstanceCache $liminalInstances;

    /**
     * @var list<array{0: class-string, 1: string|null, 2: class-string|null}>
     */
    private array $resolving = [];

    /**
     * @var ClassAttributeCache
     */
    private ClassAttributeCache $classAttributeCache;

    /**
     * @param ResolverCatalogue $resolvers
     * @param BindingCatalogue  $bindings
     */
    public function __construct(
        ResolverCatalogue $resolvers,
        BindingCatalogue  $bindings,
    ) {
        $this->resolvers           = $resolvers;
        $this->bindings            = $bindings;
        $this->instances           = InstanceCache::strong();
        $this->liminalInstances    = InstanceCache::weak();
        $this->classAttributeCache = new ClassAttributeCache();
    }

    /**
     * Resolve a class.
     *
     * @template TClass of object
     *
     * @param Resolution<TClass> $resolution
     * @param bool               $skipLazy
     *
     * @return TClass
     */
    public function resolve(Resolution $resolution, bool $skipLazy = false): object
    {
        // Resolve the actual class name, in case it's an alias.
        $class = $this->bindings->resolveAlias($resolution->class);

        // Find the binding for the class, name, and qualifier.
        $binding = $this->findBinding($resolution);

        // Set the liminal flag based on the resolution, binding, and class
        // attributes.
        $liminal = $this->isLiminal($resolution, $binding, $class);

        // If it has already been resolved, return it.
        $instance = $this->getResolved($resolution, $class, $liminal);

        if ($instance !== null) {
            return $instance;
        }

        // If it should be resolved lazily, check it can be, and return a
        // lazy proxy where it has something to defer.
        if ($skipLazy === false && $this->isLazy($resolution, $binding, $class)) {
            $lazyClass = $binding->concrete ?? $class;

            // If the binding has no instance or factory, the container would
            // construct the class, so it must be resolvable.
            if ($binding?->instance === null && $binding?->factory === null) {
                $this->guardNoResolution($class, $binding);
            }

            // If the lazy class can't be lazily instantiated, we throw an
            // exception.
            if (ReflectionHelper::canBeLazilyInstantiated($lazyClass) === false) {
                throw NotInstantiableException::make($lazyClass);
            }

            // Finally, if it has properties that can be lazily resolved, we
            // return a lazy proxy, otherwise we just resolve it normally.
            if (ReflectionHelper::hasLazyProperties($lazyClass)) {
                return $this->lazy($resolution, $lazyClass);
            }
        }

        // Otherwise, it's resolved fresh.
        return $this->resolveFresh($resolution, $class, $binding, $liminal);
    }

    public function invoke(Invocation $invocation): mixed
    {
        // If there's no class, it's a callable.
        if ($invocation->class === null) {
            $callable = $invocation->invokable;

            // Make sure that it's actually callable.
            if (! is_callable($callable)) { // @codeCoverageIgnoreStart
                throw InvalidInvocationException::notCallable();
            } // @codeCoverageIgnoreEnd

            return $this->invokeCallable($callable, $invocation->arguments);
        }

        $object = null;
        $class  = $invocation->class;
        $method = $invocation->invokable;

        // Make sure we're dealing with a proper method.
        if (! is_string($method)) { // @codeCoverageIgnoreStart
            throw InvalidInvocationException::notMethod();
        } // @codeCoverageIgnoreEnd

        // If the class is an object, we're calling a method on it.
        if (is_object($class)) {
            $object = $class;
            $class  = $object::class;
        }

        return $this->invokeClassMethod($class, $method, $invocation, $object);
    }

    /**
     * Resolve a new instance, tracking it on the resolution stack while it is
     * built, and caching it if it's shared.
     *
     * @template TClass of object
     *
     * @param Resolution<TClass>   $resolution
     * @param class-string<TClass> $class
     * @param Binding<TClass>|null $binding
     * @param bool                 $liminal
     *
     * @return TClass
     */
    private function resolveFresh(Resolution $resolution, string $class, ?Binding $binding, bool $liminal): object
    {
        // Before we resolve the class, we need to add it to the resolving stack,
        // so that we can detect circular dependencies.
        $this->enterResolutionStack($class, $resolution);

        try {
            $instance = $this->build($class, $binding);

            // If it's shared, we need to store it and return it.
            if ($binding->shared ?? true) {
                return $this->storeResolved($resolution, $instance, $liminal, $class);
            }

            // Otherwise, we just return the instance.
            return $instance;
        } finally {
            // Once everything has been done, we need to remove the class from
            // the resolving stack, so that it can be resolved again in the
            // future.
            array_pop($this->resolving);
        }
    }

    /**
     * @template TClass of object
     *
     * @param Resolution<TClass>   $resolution
     * @param Binding<TClass>|null $binding
     * @param class-string<TClass> $class
     *
     * @return bool
     */
    private function isLazy(Resolution $resolution, ?Binding $binding, string $class): bool
    {
        return $resolution->shouldResolveLazily()
               || ($binding->lazily ?? false)
               || $this->hasClassAttribute(Lazy::class, $class, $binding?->concrete);
    }

    /**
     * @template TClass of object
     *
     * @param Resolution<TClass>   $resolution
     * @param Binding<TClass>|null $binding
     * @param class-string<TClass> $class
     *
     * @return bool
     */
    private function isLiminal(Resolution $resolution, ?Binding $binding, string $class): bool
    {
        // Determine whether the resolution is liminal
        return $resolution->isLiminal()
                   || ($binding->liminal ?? false)
                   || $this->hasClassAttribute(Liminal::class, $class, $binding?->concrete);
    }

    /**
     * @template TClass of object
     *
     * @param Resolution<TClass> $resolution
     *
     * @return Binding<TClass>|null
     */
    private function findBinding(Resolution $resolution): ?Binding
    {
        // Get the binding for the class, name, and qualifier.
        $binding = $this->bindings->get(
            $resolution->class,
            $resolution->isNamed() ? new Named($resolution->name) : null,
            $resolution->qualifier,
        );

        // If there isn't, and there's a name or qualifier, we throw an
        // exception, because we can't resolve it.
        if ($binding === null) {
            if ($resolution->isNamed()) {
                throw BindingNotFoundException::named($resolution->class, $resolution->name);
            }

            if ($resolution->isQualified()) {
                throw BindingNotFoundException::qualified($resolution->class, $resolution->qualifier::class);
            }
        }
        return $binding;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     * @param Resolution<TClass>   $resolution
     */
    private function enterResolutionStack(string $class, Resolution $resolution): void
    {
        // Build the resolution entry.
        $entry = [
            $class,
            $resolution->name,
            $resolution->qualifier !== null ? $resolution->qualifier::class : null,
        ];

        $index = array_search($entry, $this->resolving, true);

        // If we're resolving a class that is already being resolved, we have a
        // circular dependency, so need to throw.
        if ($index !== false) {
            throw CircularDependencyException::make([...array_slice($this->resolving, $index), $entry]);
        }

        // Push the resolving entry onto the stack.
        $this->resolving[] = $entry;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     * @param Binding<TClass>|null $binding
     *
     * @throws UnresolvableClassException
     */
    private function guardNoResolution(string $class, ?Binding $binding): void
    {
        // If the class or its concrete class is marked as no resolution, it
        // can't be resolved automatically, so we throw, naming whichever
        // carries the attribute.
        if ($this->hasClassAttribute(NoResolution::class, $class, $binding?->concrete)) {
            throw UnresolvableClassException::make(
                $this->hasClassAttribute(NoResolution::class, $class) ? $class : ($binding->concrete ?? $class),
            );
        }
    }

    /**
     * Build a new instance for a resolution, from its binding or by
     * constructing the class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     * @param Binding<TClass>|null $binding
     *
     * @return TClass
     */
    private function build(string $class, ?Binding $binding): object
    {
        // A binding holding an instance has that instance as the result.
        if ($binding?->instance !== null) {
            return $binding->instance;
        }

        // A binding with a factory has the factory's return value as the
        // result.
        if ($binding?->factory !== null) {
            /** @var TClass */
            return $this->invoke(Invocation::callable($binding->factory));
        }

        // Otherwise, the container constructs the concrete class, or the
        // class itself where there is none, unless it's marked as 'no
        // resolution'.
        $this->guardNoResolution($class, $binding);

        return $this->instantiate($binding->concrete ?? $class);
    }

    /**
     * Construct a class, invoking its constructor through the container where
     * it has one.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     *
     * @return TClass
     */
    private function instantiate(string $class): object
    {
        $reflector = ReflectionHelper::getClassReflector($class);

        if ($reflector->isInstantiable() === false) {
            throw NotInstantiableException::make($class);
        }

        if ($reflector->hasMethod('__construct') === false) {
            return new $class();
        }

        /** @var TClass */
        return $this->invoke(Invocation::constructor($class));
    }

    /**
     * Get a previously resolved instance.
     *
     * @template TClass of object
     *
     * @param Resolution<TClass>   $resolution
     * @param class-string<TClass> $class
     * @param bool                 $liminal
     *
     * @return TClass|null
     */
    private function getResolved(Resolution $resolution, string $class, bool $liminal): ?object
    {
        $instances = $liminal ? $this->liminalInstances : $this->instances;

        if ($resolution->isNamed()) {
            /** @var TClass|null */
            return $instances->get($class, name: $resolution->name);
        }

        if ($resolution->isQualified()) {
            return $instances->get($class, qualifier: $resolution->qualifier::class);
        }

        /** @var TClass|null */
        return $instances->get($class);
    }

    /**
     * Create a lazy proxy for a resolution.
     *
     * @template TClass of object
     *
     * @param Resolution<TClass>   $resolution
     * @param class-string<TClass> $class
     *
     * @return TClass
     */
    private function lazy(Resolution $resolution, string $class): object
    {
        return ReflectionHelper::getLazyProxy(
            $class,
            function () use ($resolution, $class): object {
                $instance = $this->resolve($resolution, true);

                if ($instance::class !== $class) {
                    throw DependencyResolutionException::proxy($class, $instance::class);
                }

                return $instance;
            },
        );
    }

    /**
     * Store a resolved instance and return it.
     *
     * @template TClass of object
     *
     * @param Resolution<TClass>   $resolution
     * @param TClass               $instance
     * @param bool                 $liminal
     * @param class-string<TClass> $class
     *
     * @return TClass
     */
    private function storeResolved(Resolution $resolution, object $instance, bool $liminal, string $class): object
    {
        $instances = $liminal ? $this->liminalInstances : $this->instances;

        if ($resolution->isNamed()) {
            $instances->put($class, $instance, name: $resolution->name);
        } else if ($resolution->isQualified()) {
            $instances->put($class, $instance, qualifier: $resolution->qualifier::class);
        } else {
            $instances->put($class, $instance);
        }

        return $instance;
    }

    /**
     * @param callable             $callable
     * @param array<string, mixed> $arguments
     *
     * @return mixed
     */
    private function invokeCallable(callable $callable, array $arguments): mixed
    {
        return $callable(...$this->collectDependencies(
            ReflectionHelper::getFunctionReflector($callable),
            $arguments,
        ));
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     * @param string               $method
     * @param Invocation           $invocation
     * @param TClass|null          $object
     *
     * @return mixed
     */
    private function invokeClassMethod(string $class, string $method, Invocation $invocation, ?object $object = null): mixed
    {
        $classReflector  = ReflectionHelper::getClassReflector($class);
        $methodReflector = ReflectionHelper::getMethodReflector($class, $method);

        // If it's not public, we can't call it.
        if ($methodReflector->isPublic() === false) {
            throw InvalidInvocationException::notPublic($class, $method);
        }

        $dependencies = $this->collectDependencies($methodReflector, $invocation->arguments);

        try {
            // If it's static, calling it on an object instance is a contract
            // violation, so we throw rather than silently discard the object.
            if ($methodReflector->isStatic() === true) {
                if ($object !== null) {
                    throw InvalidInvocationException::isStatic($class, $method);
                }

                return $methodReflector->invokeArgs(null, $dependencies);
            }

            // If there's no object, there are two options...
            if ($object === null) {
                // If the method is the constructor, we're creating a new
                // instance, so we'll do just that.
                if ($methodReflector->isConstructor()) {
                    return $classReflector->newInstanceArgs($dependencies);
                }

                // Otherwise, we should resolve the object ready for the
                // catch-all and final option below.
                $object = $this->resolve(Resolution::for($class));
            }

            // If we're here, we're calling a method on an object.
            return $methodReflector->invokeArgs($object, $dependencies);
        } catch (ReflectionException $e) { // @codeCoverageIgnoreStart
            // Unreachable — the method was already successfully reflected above,
            // so invokeArgs() cannot throw a ReflectionException.
            throw MethodCallException::make($class, $method, $e);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Collect the dependencies for the given method or function.
     *
     * @param ReflectionFunctionAbstract $reflector
     * @param array<string, mixed>       $arguments
     *
     * @return array<string, mixed>
     */
    private function collectDependencies(ReflectionFunctionAbstract $reflector, array $arguments = []): array
    {
        $dependencies = [];

        foreach ($reflector->getParameters() as $parameter) {
            // If the parameter is already provided, we can just use that
            // value and skip its resolution.
            if (array_key_exists($parameter->getName(), $arguments)) {
                $dependencies[$parameter->getName()] = $arguments[$parameter->getName()];
                continue;
            }

            // We don't want to try and auto-resolve variadic parameters, so
            // we can break here. PHP also requires that variadic parameters
            // are the last ones, so this won't cause any to be skipped.
            if ($parameter->isVariadic()) {
                break;
            }

            // Create a representation of the dependency and then resolve it.
            $dependency = $this->createDependency($parameter);

            try {
                /** @phpstan-ignore argument.type */
                $dependencies[$dependency->parameter] = $this->resolveDependency($dependency);
            } catch (CircularDependencyException $e) {
                // If it's a circular dependency, we just rethrow it, because
                // it is an important exception that should take precedence.
                throw $e;
            } catch (Throwable $e) {
                // But for anything else, we wrap it in an exception that
                // specifies both the parameter, and the function or method
                // that it belongs to.
                throw DependencyResolutionException::parameter(
                    $parameter->name,
                    ReflectionHelper::getFunctionNameFromReflection($reflector),
                    previous: $e,
                );
            }
        }

        return $dependencies;
    }

    /**
     * Create a dependency representation from the given parameter.
     *
     * @param ReflectionParameter $parameter
     *
     * @return \Engine\Container\Dependency<*, *, *>
     */
    private function createDependency(ReflectionParameter $parameter): Dependency
    {
        /**
         * This has to be here, otherwise PHPStan will have wobbler. In reality,
         * it is always one of these types.
         *
         * @var ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|null $type
         */
        $type = $parameter->getType();

        return new Dependency(
            $parameter->getName(),
            $type,
            $parameter->isOptional(),
            ReflectionHelper::getAttributeInstance($parameter, Named::class),
            ReflectionHelper::getAttributeInstance($parameter, Qualifier::class, true),
            ReflectionHelper::getAttributeInstance($parameter, Resolvable::class, true),
            $parameter->isDefaultValueAvailable(),
            $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ReflectionHelper::getAttributeInstance($parameter, Liminal::class) !== null,
        );
    }

    /**
     * Resolve the given dependency.
     *
     * @template TType of mixed
     * @template TQualifier of \Engine\Container\Contracts\Qualifier|null = null
     * @template TResolvable of \Engine\Container\Contracts\Resolvable|null = null
     *
     * @param Dependency<TType, TQualifier, TResolvable> $dependency
     *
     * @return TType
     */
    private function resolveDependency(Dependency $dependency): mixed
    {
        // If there's both a name, and a qualifier, we can't resolve it.
        if ($dependency->name !== null && $dependency->qualifier !== null) {
            throw DependencyResolutionException::namedAndQualified();
        }

        return $this->getDependencyResolver($dependency)->resolve($dependency, $this);
    }

    /**
     * Get the resolver for the given dependency.
     *
     * @template TType of mixed
     * @template TResolvable of \Engine\Container\Contracts\Resolvable|null = null
     *
     * @param \Engine\Container\Dependency<TType, *, TResolvable> $dependency
     *
     * @return Resolver<TResolvable>
     */
    private function getDependencyResolver(Dependency $dependency): Resolver
    {
        // If the dependency has a resolvable attribute, we need to get its
        // resolver.
        if ($dependency->resolvable !== null) {
            $resolver = $this->resolvers->get($this, $dependency->resolvable);
        } else {
            $resolver = $this->resolvers->default($this);
        }

        /**
         * This has to be here because it complains about TDefaultResolver,
         * even though the types are the same.
         *
         * @var Resolver<TResolvable> $resolver
         */
        return $resolver;
    }

    /**
     * Check if a class has a given marker attribute.
     *
     * @param class-string<NoResolution|Liminal|Lazy> $attribute
     * @param class-string                            $class
     * @param class-string|null                       $concrete
     */
    private function hasClassAttribute(string $attribute, string $class, ?string $concrete = null): bool
    {
        return $this->classAttributeCache->has($class, $attribute)
               || ($concrete !== null && $this->classAttributeCache->has($concrete, $attribute));
    }
}
