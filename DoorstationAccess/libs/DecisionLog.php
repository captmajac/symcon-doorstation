<?php

declare(strict_types=1);

// Last access decisions, newest first, kept in an instance attribute. Source of the access list:
// the archive of the read variable misses repeated reads of the same key when the variable holds only the ID.
class DecisionLog
{
    const MAX = 20;

    private array $entries = [];  // [['ts','id','result','type','time']]; earlier versions lack type and time

    public function __construct(string $json)
    {
        $data = json_decode($json, true);
        if (is_array($data) && array_is_list($data)) {
            $this->entries = $data;
        }
    }

    public function add(string $ts, string $id, string $result, string $type, int $time): void
    {
        array_unshift($this->entries, ['ts' => $ts, 'id' => $id, 'result' => $result, 'type' => $type, 'time' => $time]);
        $this->entries = array_slice($this->entries, 0, self::MAX);
    }

    public function entries(): array
    {
        return $this->entries;
    }

    public function toJson(): string
    {
        return json_encode($this->entries);
    }
}
