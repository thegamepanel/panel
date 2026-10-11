<?php
declare(strict_types=1);

namespace Engine\Database\Query;

final readonly class WriteResult
{
    /**
     * @var array<string|int, mixed>
     */
    public readonly array $bindings;

    private int $affectedRows;

    private ?string $lastInsertId;

    /**
     * @param int                      $affectedRows
     * @param string|null              $lastInsertId
     * @param array<string|int, mixed> $bindings
     */
    public function __construct(int $affectedRows, ?string $lastInsertId, array $bindings = [])
    {
        $this->affectedRows = $affectedRows;
        $this->lastInsertId = $lastInsertId;
        $this->bindings     = $bindings;
    }

    /**
     * Get the number of affected rows.
     *
     * @return int
     */
    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    /**
     * Get the last inserted ID.
     *
     * @return string|null
     */
    public function lastInsertId(): ?string
    {
        return $this->lastInsertId;
    }

    /**
     * Check if the write operation was successful.
     *
     * @return bool
     */
    public function wasSuccessful(): bool
    {
        return $this->affectedRows > 0;
    }
}
