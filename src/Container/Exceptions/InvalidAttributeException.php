<?php

namespace Engine\Container\Exceptions;

use Engine\Container\Contracts\ContainerException;
use InvalidArgumentException;

final class InvalidAttributeException extends InvalidArgumentException implements ContainerException
{
    /**
     * @param class-string $attribute
     *
     * @return self
     */
    public static function notMarker(string $attribute): self
    {
        return new self(sprintf(
            'The attribute "%s" is not a class-level marker the container tracks',
            $attribute,
        ));
    }
}
