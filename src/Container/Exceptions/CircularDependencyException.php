<?php
declare(strict_types=1);

namespace Engine\Container\Exceptions;

use Engine\Container\Contracts\ContainerException;
use RuntimeException;

final class CircularDependencyException extends RuntimeException implements ContainerException
{
    /**
     * @param list<array{0: class-string, 1: string|null, 2: class-string|null}> $chain
     *
     * @return self
     */
    public static function make(array $chain): self
    {
        return new self(sprintf(
            'Circular dependency detected: %s.',
            implode(' -> ', array_map(
                static fn (array $entry) => match (true) {
                    $entry[1] !== null => sprintf('"%s" named "%s"', $entry[0], $entry[1]),
                    $entry[2] !== null => sprintf('"%s" qualified by "%s"', $entry[0], $entry[2]),
                    default            => sprintf('"%s"', $entry[0]),
                },
                $chain,
            )),
        ));
    }
}
