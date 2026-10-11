<?php
declare(strict_types=1);

namespace Engine\Container\Exceptions;

use Engine\Container\Contracts\ContainerException;
use InvalidArgumentException;
use Throwable;

final class NotInstantiableException extends InvalidArgumentException implements ContainerException
{
    /**
     * @param class-string   $class
     * @param Throwable|null $previous
     *
     * @return self
     */
    public static function make(string $class, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Class %s is not instantiable', $class),
            previous: $previous,
        );
    }
}
