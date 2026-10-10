<?php

namespace Engine\Container;

use Engine\Container\Attributes\Lazy;
use Engine\Container\Attributes\Liminal;
use Engine\Container\Attributes\NoResolution;
use Engine\Container\Exceptions\InvalidAttributeException;

/**
 * Class Attribute Cache
 * ---------------------
 *
 * This class is responsible for caching the attributes of classes to optimise
 * the resolution process in the container. It checks if a class has specific
 * attributes (NoResolution, Liminal, Lazy) and caches the results for future
 * lookups.
 *
 * @internal
 */
final class ClassAttributeCache
{
    /**
     * The supported attributes.
     */
    private const array MARKERS = [
        NoResolution::class, Liminal::class, Lazy::class,
    ];

    /**
     * Cache of class attributes.
     *
     * @var array<class-string, array<class-string<NoResolution|Liminal|Lazy>, bool>>
     */
    private array $attributes = [];

    /**
     * Check if a class has a specific attribute.
     *
     * @param class-string                            $class
     * @param class-string<NoResolution|Liminal|Lazy> $attribute
     *
     * @return bool
     *
     * @throws InvalidAttributeException if the attribute is not a supported marker
     */
    public function has(string $class, string $attribute): bool
    {
        if (! in_array($attribute, self::MARKERS, true)) {
            throw InvalidAttributeException::notMarker($attribute);
        }

        return ($this->attributes[$class] ?? $this->populate($class))[$attribute];
    }

    /**
     * Populate the cache for a class.
     *
     * @param class-string $class
     *
     * @return array<class-string<NoResolution|Liminal|Lazy>, bool>
     */
    private function populate(string $class): array
    {
        $attributes      = ReflectionHelper::getClassAttributes($class);
        $classAttributes = array_fill_keys(self::MARKERS, false);

        foreach ($attributes as $attribute) {
            if (in_array($attribute->getName(), self::MARKERS, true)) {
                $classAttributes[$attribute->getName()] = true;
            }
        }

        return $this->attributes[$class] = $classAttributes;
    }
}
