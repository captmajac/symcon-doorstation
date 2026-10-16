<?php

declare(strict_types=1);

// Last accesses from archived values of the read variable.
class RecentList
{
    // $rows: archive rows ['TimeStamp','Value'], newest first; $current: current value of the variable (may be
    // newer than the archive, which writes buffered); $nameOf(string $id): ?string
    // Returns [['time','id','name','type','result']], newest first.
    public static function build(array $rows, ?array $current, callable $nameOf, DecisionLog $log, int $limit = 5): array
    {
        if ($current !== null && ($rows === [] || ($current['TimeStamp'] > $rows[0]['TimeStamp'] && $current['Value'] !== $rows[0]['Value']))) {
            array_unshift($rows, $current);
        }
        $list = [];
        foreach ($rows as $row) {
            $read = ReadParser::parse((string) $row['Value'], 0, (int) $row['TimeStamp']);
            if (!$read['ok']) {
                continue;
            }
            $list[] = [
                'time'   => (int) $row['TimeStamp'],
                'id'     => $read['id'],
                'name'   => $nameOf($read['id']),
                'type'   => $read['type'],
                'result' => $log->find($read['ts'], $read['id']),
            ];
            if (count($list) === $limit) {
                break;
            }
        }
        return $list;
    }
}
