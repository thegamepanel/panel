<?php
declare(strict_types=1);

namespace Engine\Container\Bindings;

use Engine\Container\Attributes\Named;
use Engine\Container\Contracts\Qualifier;

final readonly class BindingCatalogue
{
    /**
     * The collection of registered bindings.
     *
     * @var array<class-string, \Engine\Container\Bindings\Binding<*>>
     */
    public array $bindings;

    /**
     * The collection of aliases mapped to their bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $aliases;

    /**
     * The collection of scopes mapped to their bindings.
     *
     * @var array<string, array<class-string>>
     */
    public array $scoped;

    /**
     * @param array<class-string, \Engine\Container\Bindings\Binding<*>> $bindings
     * @param array<class-string, class-string>  $aliases
     * @param array<string, array<class-string>> $scoped
     */
    public function __construct(
        array $bindings,
        array $aliases,
        array $scoped,
    ) {
        $this->bindings = $bindings;
        $this->aliases  = $aliases;
        $this->scoped   = $scoped;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     * @param Named|null           $named
     * @param Qualifier|null       $qualifier
     *
     * @return Binding<TClass>|null
     */
    public function get(string $class, ?Named $named = null, ?Qualifier $qualifier = null): ?Binding
    {
        $class = $this->resolveAlias($class);

        if (! isset($this->bindings[$class])) {
            return null;
        }

        /** @var Binding<TClass> $binding */
        $binding = $this->bindings[$class];

        if ($named !== null) {
            return $binding->namedMap[$named->name] ?? null;
        }

        if ($qualifier !== null) {
            return $binding->qualifiedMap[$qualifier::class] ?? null;
        }

        return $binding;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     *
     * @return class-string<TClass>
     */
    public function resolveAlias(string $class): string
    {
        /** @noinspection ProperNullCoalescingOperatorUsageInspection */
        /** @var class-string<TClass> */
        return $this->aliases[$class] ?? $class;
    }
}
