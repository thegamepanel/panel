<?php
declare(strict_types=1);

namespace Engine\Container\Resolvers;

use Engine\Container\Container;
use Engine\Container\Contracts\Resolver;
use Engine\Container\Dependency;
use Engine\Container\Exceptions\DependencyResolutionException;
use Engine\Container\Invocation;
use Engine\Container\ReflectionHelper;

/**
 * @implements \Engine\Container\Contracts\Resolver<\Engine\Container\Attributes\Ghost>
 */
final class GhostResolver implements Resolver
{
    /**
     * Resolve a dependency.
     *
     * @template TType of mixed
     *
     * @param \Engine\Container\Dependency<TType, *, \Engine\Container\Attributes\Ghost> $dependency
     * @param Container            $container
     * @param array<string, mixed> $arguments
     *
     * @return TType&object
     */
    public function resolve(Dependency $dependency, Container $container, array $arguments = []): mixed
    {
        /** @var string|null $class */
        $class = ReflectionHelper::getTypeClassName($dependency);

        if ($class === null || (! class_exists($class) && ! interface_exists($class))) {
            throw DependencyResolutionException::ghost($class ?? 'null');
        }

        /**
         * @var class-string<TType&object> $class
         * @var class-string<TType&object> $concreteClass
         */
        $concreteClass = $container->findBinding($class, $dependency->name, $dependency->qualifier)->concrete ?? $class;

        /** @var TType&object $instance */
        $instance = ReflectionHelper::getClassReflector($concreteClass)
            ->newLazyGhost(function (object $lazy) use ($container, $arguments): void {
                // Invoke the constructor for this class if it has one.
                if (method_exists($lazy, '__construct')) {
                    $container->invoke(Invocation::constructor($lazy)->with($arguments));
                }
            })
        ;

        return $instance;
    }
}
