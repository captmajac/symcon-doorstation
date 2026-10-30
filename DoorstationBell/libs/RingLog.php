<?php

declare(strict_types=1);

// Last rings, newest first, kept in an instance attribute. Source of the ring history:
// the bell variable may get the same value on every ring, the archive would miss repeated rings.
class RingLog
{
    const MAX = 20;

    private array $entries = [];  // [['time', 'muted']]

    public function __construct(string $json)
    {
        $data = json_decode($json, true);
        if (is_array($data) && array_is_list($data)) {
            $this->entries = $data;
        }
    }

    public function add(int $time, bool $muted): void
    {
        array_unshift($this->entries, ['time' => $time, 'muted' => $muted]);
        $this->entries = array_slice($this->entries, 0, self::MAX);
    }

    public function latest(int $n): array
    {
        return array_slice($this->entries, 0, $n);
    }

    public function toJson(): string
    {
        return json_encode($this->entries);
    }
}
