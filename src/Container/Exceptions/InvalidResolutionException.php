<?php

namespace Engine\Container\Exceptions;

use Engine\Container\Contracts\ContainerException;
use InvalidArgumentException;

final class InvalidResolutionException extends InvalidArgumentException implements ContainerException
{
    /**
     * Used when a class is attempted to be resolved with both a name and a qualifier.
     *
     * @param class-string $class
     * @param string       $name
     * @param class-string $qualifier
     *
     * @return InvalidResolutionException
     */
    public static function doubleIdentifiedClass(string $class, string $name, string $qualifier): self
    {
        return new self(sprintf(
            'Class "%s" cannot have both a name "%s", and qualifier "%s".',
            $class,
            $name,
            $qualifier,
        ));
    }
}
