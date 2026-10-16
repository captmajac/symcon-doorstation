<?php

// DecisionLog (stored decisions) and RecentList (last accesses from the archive)

function row(int $time, string $id, string $ts = null, string $type = 'card'): array
{
    return ['TimeStamp' => $time, 'Value' => json_encode(['ts' => $ts ?? "ts$time", 'type' => $type, 'id' => $id])];
}

$tests['Log: 21 entries keep 20, oldest dropped; find by ts+id'] = function () {
    $log = new DecisionLog('[]');
    for ($i = 1; $i <= 21; $i++) {
        $log->add("ts$i", 'aaaaaaa' . dechex($i % 16), 'allowed');
    }
    check(count(json_decode($log->toJson(), true)) === 20, 'not 20');
    check($log->find('ts1', 'aaaaaaa1') === null, 'oldest still there');
    check($log->find('ts21', 'aaaaaaa5') === 'allowed', 'newest missing');
    check($log->find('ts20', 'aaaaaaa5') === null, 'other ts matched');
    $again = new DecisionLog($log->toJson());
    check($again->find('ts21', 'aaaaaaa5') === 'allowed', 'not restored from JSON');
};

$tests['Log: broken JSON gives empty log'] = function () {
    check((new DecisionLog('broken'))->toJson() === '[]', 'not empty');
    check((new DecisionLog('{"a":1}'))->toJson() === '[]', 'object accepted');
};

$tests['List: 7 valid rows give 5 newest with name and result'] = function () {
    $rows = [];
    for ($t = 7; $t >= 1; $t--) {
        $rows[] = row($t, 'c0ffee42');
    }
    $log = new DecisionLog('[]');
    $log->add('ts7', 'c0ffee42', 'allowed');
    $list = RecentList::build($rows, null, fn ($id) => $id === 'c0ffee42' ? 'Card garden' : null, $log);
    check(array_column($list, 'time') === [7, 6, 5, 4, 3], json_encode(array_column($list, 'time')));
    check($list[0] === ['time' => 7, 'id' => 'c0ffee42', 'name' => 'Card garden', 'type' => 'card', 'result' => 'allowed'], json_encode($list[0]));
    check($list[1]['result'] === null, 'result for unknown decision');
};

$tests['List: current newer than first row comes first, same value not twice'] = function () {
    $rows = [row(5, 'c0ffee42'), row(4, 'c0ffee42')];
    $newer = row(9, '04a1b2c3d4e5f6');
    $list = RecentList::build($rows, $newer, fn ($id) => null, new DecisionLog('[]'));
    check(array_column($list, 'time') === [9, 5, 4], json_encode($list));
    $same = ['TimeStamp' => 6, 'Value' => $rows[0]['Value']];
    $list = RecentList::build($rows, $same, fn ($id) => null, new DecisionLog('[]'));
    check(array_column($list, 'time') === [5, 4], 'duplicate: ' . json_encode($list));
};

$tests['List: current older than first row ignored'] = function () {
    $list = RecentList::build([row(5, 'c0ffee42')], row(3, '04a1b2c3d4e5f6'), fn ($id) => null, new DecisionLog('[]'));
    check(array_column($list, 'time') === [5], json_encode($list));
};

$tests['List: rows that are neither JSON nor an ID, empty or without id are skipped'] = function () {
    $rows = [
        ['TimeStamp' => 9, 'Value' => 'hello'],
        ['TimeStamp' => 8, 'Value' => ''],
        ['TimeStamp' => 7, 'Value' => '{"ts":"x"}'],
        row(6, 'c0ffee42'),
    ];
    $list = RecentList::build($rows, null, fn ($id) => null, new DecisionLog('[]'));
    check(array_column($list, 'time') === [6], json_encode($list));
};

$tests['List: upper-case ID from archive, unknown name null'] = function () {
    $list = RecentList::build([row(5, '04A1B2C3D4E5F6', null, 'homekey')], null, fn ($id) => null, new DecisionLog('[]'));
    check($list[0]['id'] === '04a1b2c3d4e5f6' && $list[0]['name'] === null && $list[0]['type'] === 'homekey', json_encode($list));
};

$tests['List: decision found with upper-case archive ID'] = function () {
    $log = new DecisionLog('[]');
    $log->add('ts5', '04a1b2c3d4e5f6', 'unknown');
    $list = RecentList::build([row(5, '04A1B2C3D4E5F6')], null, fn ($id) => null, $log);
    check($list[0]['result'] === 'unknown', json_encode($list));
};
