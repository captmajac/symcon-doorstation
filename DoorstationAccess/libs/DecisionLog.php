<?php

declare(strict_types=1);

// Last access decisions, newest first, kept in an instance attribute.
class DecisionLog
{
    const MAX = 20;

    private array $entries = [];  // [['ts','id','result']]

    public function __construct(string $json)
    {
        $data = json_decode($json, true);
        if (is_array($data) && array_is_list($data)) {
            $this->entries = $data;
        }
    }

    public function add(string $ts, string $id, string $result): void
    {
        array_unshift($this->entries, ['ts' => $ts, 'id' => $id, 'result' => $result]);
        $this->entries = array_slice($this->entries, 0, self::MAX);
    }

    public function find(string $ts, string $id): ?string
    {
        foreach ($this->entries as $entry) {
            if (($entry['ts'] ?? null) === $ts && ($entry['id'] ?? null) === $id) {
                return $entry['result'] ?? null;
            }
        }
        return null;
    }

    public function toJson(): string
    {
        return json_encode($this->entries);
    }
}
