<?php
declare(strict_types=1);

namespace Engine\Container;

use Engine\Container\Attributes\Lazy;
use Engine\Container\Attributes\Liminal;
use Engine\Container\Attributes\Named;
use Engine\Container\Attributes\NoResolution;
use Engine\Container\Bindings\BindingCatalogue;
use Engine\Container\Contracts\Qualifier;
use Engine\Container\Contracts\Resolvable;
use Engine\Container\Contracts\Resolver;
use Engine\Container\Exceptions\BindingNotFoundException;
use Engine\Container\Exceptions\DependencyResolutionException;
use Engine\Container\Exceptions\InvalidInvocationException;
use Engine\Container\Exceptions\MethodCallException;
use Engine\Container\Exceptions\NotInstantiableException;
use Engine\Container\Exceptions\UnresolvableClassException;
use Engine\Container\Resolvers\ResolverCatalogue;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionParameter;
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
        $class = $this->bindings->resolveAlias($resolution->class);

        // If it has already been resolved, return it.
        $instance = $this->getResolved($resolution, $class);

        if ($instance !== null) {
            return $instance;
        }

        // If it should be resolved lazily, return a lazy proxy, deferring the
        // resolution until needed.
        if ($skipLazy === false && $resolution->shouldResolveLazily()) {
            return $this->lazy($resolution);
        }

        // If we're here, see if there's a binding.
        $binding = $this->bindings->get(
            $resolution->class,
            $resolution->isNamed() ? new Named($resolution->name) : null,
            $resolution->qualifier,
        );

        if ($binding === null) {
            // If there's no binding, but the resolution is named or qualified,
            // it's an error. So we throw.
            if ($resolution->isNamed()) {
                throw BindingNotFoundException::named($resolution->class, $resolution->name);
            }

            if ($resolution->isQualified()) {
                throw BindingNotFoundException::qualified($resolution->class, $resolution->qualifier::class);
            }
        }

        // Grab the values and flags.
        $instance       = $binding?->instance;
        $shared         = $binding->shared ?? true;
        $liminal        = ($binding->liminal ?? false) || $resolution->isLiminal();
        $resolvingClass = $class;

        // If we have no instance but a binding, we can either invoke the
        // factory from the binding if one exists or use the concrete class.
        if ($instance === null && $binding !== null) {
            if ($binding->factory !== null) {
                $instance = $this->invoke(Invocation::callable($binding->factory));
            } else if ($binding->concrete !== null) {
                $resolvingClass = $binding->concrete;
            }
        }

        // If we still don't have an instance, we need to create one ourselves.
        if ($instance === null) {
            $reflector = ReflectionHelper::getClassReflector($resolvingClass);

            // If we're here, and it has the 'no resolution' attribute, we can't
            // automatically resolve it, so it's an exception.
            if ($this->hasClassAttribute(NoResolution::class, $class, $binding?->concrete)) {
                throw UnresolvableClassException::make(
                    $this->hasClassAttribute(NoResolution::class, $class) ? $class : $resolvingClass,
                );
            }

            // If it has the lazy attribute, it needs a lazy resolution.
            if ($skipLazy === false && $this->hasClassAttribute(Lazy::class, $class, $binding?->concrete)) {
                return $this->lazy($resolution);
            }

            // We first need to make sure the class is instantiable.
            if ($reflector->isInstantiable() === false) {
                throw NotInstantiableException::make($resolvingClass);
            }

            // Then we check if there's a constructor...
            if ($reflector->hasMethod('__construct') === false) {
                // Because if there isn't, we instantiate like normal...
                $instance = new $resolvingClass();
            } else {
                // But if there is, we need to invoke the constructor.
                $instance = $this->invoke(Invocation::constructor($resolvingClass));
            }

            // Finally, if the liminal flag isn't already set, we set it based
            // on the presence of the Liminal attribute.
            $liminal = $liminal || $this->hasClassAttribute(Liminal::class, $class, $binding?->concrete);
        }

        /**
         * This is just here to ensure that everything knows the type correctly.
         *
         * @var TClass $instance
         */

        // If it's shared, we need to store it and return it.
        if ($shared) {
            return $this->storeResolved($resolution, $instance, $liminal, $class);
        }

        // Otherwise, we just return the instance.
        return $instance;
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
     * Get a previously resolved instance.
     *
     * @template TClass of object
     *
     * @param Resolution<TClass>   $resolution
     * @param class-string<TClass> $class
     *
     * @return TClass|null
     */
    private function getResolved(Resolution $resolution, string $class): ?object
    {
        $instances = $resolution->isLiminal() ? $this->liminalInstances : $this->instances;

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
     * @param Resolution<TClass> $resolution
     *
     * @return TClass
     */
    private function lazy(Resolution $resolution): object
    {
        return ReflectionHelper::getLazyProxy(
            $resolution->class,
            fn () => $this->resolve($resolution, true),
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
            } catch (Throwable $e) {
                // If there's any sort of error, we wrap it in an exception that
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
         * @var \ReflectionNamedType|\ReflectionUnionType|\ReflectionIntersectionType|null $type
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
