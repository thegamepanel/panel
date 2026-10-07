<?php

namespace Engine\Container;

use Engine\Container\Exceptions\InvalidResolutionException;
use WeakReference;

/**
 * Instance Cache
 * --------------
 *
 * This class is used to cache resolved instances of caches, whether they're
 * resolved directly by type, name, or qualifier. It supports both strong and
 * weak references, allowing for flexible memory management.
 *
 * @internal this class is intended for internal use within the container and should not be used directly by external code
 *
 * @template TWeak of true|false
 */
final class InstanceCache
{
    /**
     * Create a new instance of the cache.
     *
     * @return self<false>
     */
    public static function strong(): self
    {
        return new self(false);
    }

    /**
     * Create a new instance of the cache with weak references.
     *
     * @return self<true>
     */
    public static function weak(): self
    {
        return new self(true);
    }

    /**
     * Resolved instances, mapped by class name.
     *
     * @var array<class-string, object|WeakReference>
     */
    private array $instances = [];

    /**
     * Resolved instances, mapped by class name and 'name'.
     *
     * @var array<class-string, array<string, object|WeakReference>>
     */
    private array $namedInstances = [];

    /**
     * Resolved instances, mapped by class name and 'qualifier'.
     *
     * @var array<class-string, array<class-string, object|WeakReference>>
     */
    private array $qualifiedInstances = [];

    /**
     * @param TWeak $weak
     */
    private function __construct(
        private readonly bool $weak,
    ) {
    }

    /**
     * Store an instance in the cache.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     * @param TClass               $instance
     * @param string|null          $name
     * @param class-string|null    $qualifier
     *
     * @return static
     */
    public function put(string $class, object $instance, ?string $name = null, ?string $qualifier = null): self
    {
        if ($name !== null && $qualifier !== null) {
            throw InvalidResolutionException::doubleIdentifiedClass($class, $name, $qualifier);
        }

        $storageInstance = $this->forStorage($instance);

        if ($name !== null) {
            $this->namedInstances[$class][$name] = $storageInstance;
        } else if ($qualifier !== null) {
            $this->qualifiedInstances[$class][$qualifier] = $storageInstance;
        } else {
            $this->instances[$class] = $storageInstance;
        }

        return $this;
    }

    /**
     * Get an instance from the cache.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $class
     * @param string|null          $name
     * @param class-string|null    $qualifier
     *
     * @return TClass|null
     */
    public function get(string $class, ?string $name = null, ?string $qualifier = null): ?object
    {
        // No exception is thrown here if both a name and qualifier are
        // provided, because we just default to definition order.
        if ($name !== null) {
            $instance = $this->namedInstances[$class][$name] ?? null;
        } else if ($qualifier !== null) {
            $instance = $this->qualifiedInstances[$class][$qualifier] ?? null;
        } else {
            $instance = $this->instances[$class] ?? null;
        }

        // The weak reference is intentionally left in place if it's null,
        // because it really doesn't hurt to have a null weak reference. It will
        // be overwritten with a new instance if the class is resolved again.
        if ($instance instanceof WeakReference) {
            /** @var TClass|null */
            return $instance->get();
        }

        /** @var TClass|null */
        return $instance;
    }

    /**
     * Get an instance for storage.
     *
     * If the cache is weak the instance is wrapped in {@see WeakReference},
     * otherwise it's returned as is.
     *
     * @template TClass of object
     *
     * @param TClass $instance
     *
     * @return (TWeak is true ? WeakReference<TClass> : TClass)
     */
    private function forStorage(object $instance): object
    {
        return $this->weak ? WeakReference::create($instance) : $instance;
    }
}
