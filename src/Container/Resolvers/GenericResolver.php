<?php
declare(strict_types=1);

namespace Engine\Container\Resolvers;

use Engine\Container\Container;
use Engine\Container\Contracts\Resolver;
use Engine\Container\Dependency;
use Engine\Container\Exceptions\CircularDependencyException;
use Engine\Container\Exceptions\DependencyResolutionException;
use Engine\Container\Exceptions\InvalidClassException;
use Engine\Container\Invocation;
use Engine\Container\ReflectionHelper;
use Engine\Container\Resolution;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;

/**
 * @implements \Engine\Container\Contracts\Resolver<null>
 */
final class GenericResolver implements Resolver
{
    /**
     * Resolve a dependency.
     *
     * @template TType of mixed
     *
     * @param \Engine\Container\Dependency<TType, *, null> $dependency
     * @param Container            $container
     * @param array<string, mixed> $arguments
     *
     * @return TType|(TType&object)|null
     */
    public function resolve(Dependency $dependency, Container $container, array $arguments = []): mixed
    {
        if (ReflectionHelper::isSingleType($dependency)) {
            return $this->resolveSingleType($dependency->type, $dependency, $container, $arguments);
        }

        if (ReflectionHelper::isIntersectionType($dependency)) {
            return $this->resolveIntersectionType($dependency->type, $dependency, $container, $arguments);
        }

        if (ReflectionHelper::isUnionType($dependency)) {
            return $this->resolveUnionType($dependency->type, $dependency, $container, $arguments);
        }

        if ($dependency->hasDefault) {
            return $dependency->default;
        }

        /** @var \ReflectionType|null $type */
        $type = $dependency->type;

        throw DependencyResolutionException::cannotResolve($type?->__toString() ?? 'unknown');
    }

    /**
     * @template TClass of object
     *
     * @param Resolution<TClass> $resolution
     * @param \Engine\Container\Dependency<*, *, *> $dependency
     */
    private function configureResolution(Resolution $resolution, Dependency $dependency): void
    {
        if ($dependency->name !== null) {
            $resolution->named($dependency->name->name);
        }

        if ($dependency->qualifier !== null) {
            $resolution->qualifiedBy($dependency->qualifier);
        }

        if ($dependency->liminal) {
            $resolution->liminal();
        }
    }

    /**
     * Resolve a dependency by type.
     *
     * @template TType of mixed
     *
     * @param ReflectionNamedType $type
     * @param \Engine\Container\Dependency<TType, *, *> $dependency
     * @param Container            $container
     * @param array<string, mixed> $arguments
     *
     * @return TType|(TType&object)|null
     */
    private function resolveSingleType(ReflectionNamedType $type, Dependency $dependency, Container $container, array $arguments = []): mixed
    {
        if (! class_exists($type->getName()) && ! interface_exists($type->getName())) {
            if ($dependency->hasDefault) {
                return $dependency->default;
            }

            if ($type->allowsNull()) {
                return null;
            }

            throw DependencyResolutionException::cannotResolve($type->getName());
        }

        $this->configureResolution(
            $resolution = Resolution::for($type->getName())->with($arguments),
            $dependency,
        );

        /** @var TType&object $instance */
        $instance = $container->resolve($resolution);

        return $instance;
    }

    /**
     * Resolve a dependency by type.
     *
     * @template TType of mixed
     *
     * @param ReflectionIntersectionType $type
     * @param \Engine\Container\Dependency<TType, *, *> $dependency
     * @param Container            $container
     * @param array<string, mixed> $arguments
     *
     * @return (TType&object)|null
     */
    private function resolveIntersectionType(ReflectionIntersectionType $type, Dependency $dependency, Container $container, array $arguments = []): ?object
    {
        /** @var array<ReflectionNamedType> $types */
        $types    = $type->getTypes();
        $bindings = [];
        $classes  = [];

        foreach ($types as $subType) {
            /**
             * @var array<class-string> $classes
             * @var class-string        $className
             */
            $classes[] = $className = $subType->getName();
            $binding   = $container->bindings->get($className, $dependency->name, $dependency->qualifier);

            if ($binding !== null) {
                $bindings[] = $binding;
            }
        }

        if (empty($bindings)) {
            if ($dependency->hasDefault) {
                /** @var (TType&object)|null */
                return $dependency->default;
            }

            throw DependencyResolutionException::intersectionNoBinding($type->__toString());
        }

        foreach ($bindings as $binding) {
            try {
                if ($binding->isBoundToInstance()) {
                    $instance = $binding->instance;
                } else if ($binding->concrete !== null) {
                    /** @var class-string $bindingClass */
                    $bindingClass = $binding->concrete;
                    $this->configureResolution(
                        $resolution = Resolution::for($bindingClass)->with($arguments),
                        $dependency,
                    );
                    $instance = $container->resolve($resolution);
                } else if ($binding->hasFactory()) {
                    $instance = $container->invoke(
                        Invocation::callable($binding->factory)->with($arguments),
                    );
                } else {
                    // If we're here, this binding is a pass.
                    continue;
                }

                if (array_all($classes, static fn ($class) => $instance instanceof $class)) {
                    /** @var TType&object $instance */
                    return $instance;
                }
            } catch (InvalidClassException) {
                continue;
            }
        }

        if ($dependency->hasDefault) {
            /** @var (TType&object)|null */
            return $dependency->default;
        }

        throw DependencyResolutionException::intersection($type->__toString());
    }

    /**
     * Resolve a dependency by type.
     *
     * @template TType of mixed
     *
     * @param ReflectionUnionType $type
     * @param \Engine\Container\Dependency<TType, *, *> $dependency
     * @param Container            $container
     * @param array<string, mixed> $arguments
     *
     * @return TType|(TType&object)|null
     */
    private function resolveUnionType(ReflectionUnionType $type, Dependency $dependency, Container $container, array $arguments = []): mixed
    {
        /** @var array<ReflectionNamedType|ReflectionIntersectionType> $types */
        $types           = $type->getTypes();
        $resolvableTypes = [];

        foreach ($types as $subType) {
            if ($subType instanceof ReflectionNamedType && (class_exists($subType->getName()) || interface_exists($subType->getName()))) {
                $resolvableTypes[] = $subType;
                continue;
            }

            if ($subType instanceof ReflectionIntersectionType) {
                $resolvableTypes[] = $subType;
            }
        }

        if (! empty($resolvableTypes) && count($resolvableTypes) === 1) {
            $resolvable = $resolvableTypes[0];

            try {
                if ($resolvable instanceof ReflectionNamedType) {
                    /** @var TType&object */
                    return $this->resolveSingleType($resolvable, $dependency, $container, $arguments);
                }

                /** @var TType&object */
                return $this->resolveIntersectionType($resolvable, $dependency, $container, $arguments);
            } catch (CircularDependencyException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw DependencyResolutionException::union($type->__toString(), previous: $e);
            }
        }

        if ($dependency->hasDefault) {
            /** @var TType|(TType&object)|null */
            return $dependency->default;
        }

        if ($type->allowsNull()) {
            return null;
        }

        throw DependencyResolutionException::union($type->__toString());
    }
}
