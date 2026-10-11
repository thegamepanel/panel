<?php
declare(strict_types=1);

namespace Engine\Values\Contracts;

use Engine\Values\Exceptions\InvalidValueCastException;

interface GetsAsType
{
    /**
     * Get a value as a string.
     *
     * @param string $name
     *
     * @return string
     *
     * @throws InvalidValueCastException
     */
    public function string(string $name): string;

    /**
     * Get a value as an integer.
     *
     * @param string $name
     *
     * @return int
     *
     * @throws InvalidValueCastException
     */
    public function int(string $name): int;

    /**
     * Get a value as a float.
     *
     * @param string $name
     *
     * @return float
     *
     * @throws InvalidValueCastException
     */
    public function float(string $name): float;

    /**
     * Get a value as a boolean.
     *
     * @param string $name
     *
     * @return bool
     *
     * @throws InvalidValueCastException
     */
    public function bool(string $name): bool;

    /**
     * Get a value as an array.
     *
     * @param string $name
     *
     * @return array<mixed>
     *
     * @throws InvalidValueCastException
     */
    public function array(string $name): array;
}
