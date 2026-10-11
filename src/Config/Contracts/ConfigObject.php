<?php
declare(strict_types=1);

namespace Engine\Config\Contracts;

/**
 * Config Object
 * -------------
 *
 * Interface represents the concept of a configuration object, used by the
 * config component.
 */
interface ConfigObject
{
    /**
     * Create a new config object from an array.
     *
     * Creates a new instance of the config object using the provided data
     * pulled from the config storage.
     *
     * @param array<array-key, mixed> $data
     *
     * @return static
     */
    public static function fromArray(array $data): static;
}
