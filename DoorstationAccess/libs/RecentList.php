<?php

declare(strict_types=1);

// Last accesses for display, from the decision log.
class RecentList
{
    // $nameOf(string $id): ?string
    // Returns [['time','id','name','type','result']], newest first.
    public static function build(DecisionLog $log, callable $nameOf, int $limit = 5): array
    {
        $list = [];
        foreach (array_slice($log->entries(), 0, $limit) as $entry) {
            $id = (string) ($entry['id'] ?? '');
            $list[] = [
                'time'   => (int) ($entry['time'] ?? strtotime((string) ($entry['ts'] ?? ''))),
                'id'     => $id,
                'name'   => $nameOf($id),
                // Home Key IDs have 8 bytes, card UIDs 4, 7 or 10
                'type'   => $entry['type'] ?? (strlen($id) === 16 ? 'homekey' : 'card'),
                'result' => $entry['result'] ?? null,
            ];
        }
        return $list;
    }
}
