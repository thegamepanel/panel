<?php
declare(strict_types=1);

namespace Engine\Values;

use Engine\Values\Exceptions\InvalidValueCastException;
use JsonException;

class ValueGetter
{
    /**
     * Get a value as a boolean.
     *
     * @param string               $name
     * @param array<string, mixed> $values
     *
     * @return bool
     *
     * @throws InvalidValueCastException
     */
    public static function bool(string $name, array $values): bool
    {
        $value = $values[$name] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return match ($value) {
                'true', '1', 'yes' => true,
                'false', '0', 'no' => false,
                default            => throw InvalidValueCastException::cannotCast($name, 'a boolean'),
            };
        }

        throw InvalidValueCastException::cannotCast($name, 'a boolean');
    }

    /**
     * Get a value as an array.
     *
     * @param string               $name
     * @param array<string, mixed> $values
     *
     * @return array<mixed>
     *
     * @throws InvalidValueCastException
     * @throws JsonException
     */
    public static function array(string $name, array $values): array
    {
        $value = $values[$name] ?? null;

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && json_validate($value)) {
            /** @var array<mixed> */
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        throw InvalidValueCastException::cannotCast($name, 'an array');
    }

    /**
     * Get a value as a string.
     *
     * @param string               $name
     * @param array<string, mixed> $values
     *
     * @return string
     *
     * @throws InvalidValueCastException
     */
    public static function string(string $name, array $values): string
    {
        $value = $values[$name] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        throw InvalidValueCastException::cannotCast($name, 'a string');
    }

    /**
     * Get a value as a float.
     *
     * @param string               $name
     * @param array<string, mixed> $values
     *
     * @return float
     *
     * @throws InvalidValueCastException
     */
    public static function float(string $name, array $values): float
    {
        $value = $values[$name] ?? null;

        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        throw InvalidValueCastException::cannotCast($name, 'a float');
    }

    /**
     * Get a value as an integer.
     *
     * @param string               $name
     * @param array<string, mixed> $values
     *
     * @return int
     *
     * @throws InvalidValueCastException
     */
    public static function int(string $name, array $values): int
    {
        $value = $values[$name] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw InvalidValueCastException::cannotCast($name, 'an integer');
    }
}
