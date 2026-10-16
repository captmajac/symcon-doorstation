<?php

// DecisionLog (own record of the last decisions) and RecentList (last accesses for display)

function logWith(array $entries): DecisionLog
{
    $log = new DecisionLog('[]');
    foreach (array_reverse($entries) as [$time, $id, $result, $type]) {
        $log->add("ts$time", $id, $result, $type, $time);
    }
    return $log;
}

$tests['Log: 21 entries keep 20, newest first, restored from JSON'] = function () {
    $log = new DecisionLog('[]');
    for ($i = 1; $i <= 21; $i++) {
        $log->add("ts$i", 'c0ffee42', 'allowed', 'card', $i);
    }
    $entries = $log->entries();
    check(count($entries) === 20, 'not 20');
    check($entries[0] === ['ts' => 'ts21', 'id' => 'c0ffee42', 'result' => 'allowed', 'type' => 'card', 'time' => 21], json_encode($entries[0]));
    check(end($entries)['time'] === 2, 'oldest still there');
    check((new DecisionLog($log->toJson()))->entries() === $entries, 'not restored from JSON');
};

$tests['Log: broken JSON gives empty log'] = function () {
    check((new DecisionLog('broken'))->toJson() === '[]', 'not empty');
    check((new DecisionLog('{"a":1}'))->toJson() === '[]', 'object accepted');
};

$tests['List: 7 entries give 5 newest with name, type and result'] = function () {
    $entries = [];
    for ($t = 7; $t >= 1; $t--) {
        $entries[] = [$t, 'c0ffee42', $t === 7 ? 'allowed' : 'unknown', 'card'];
    }
    $list = RecentList::build(logWith($entries), fn ($id) => $id === 'c0ffee42' ? 'Card garden' : null);
    check(array_column($list, 'time') === [7, 6, 5, 4, 3], json_encode(array_column($list, 'time')));
    check($list[0] === ['time' => 7, 'id' => 'c0ffee42', 'name' => 'Card garden', 'type' => 'card', 'result' => 'allowed'], json_encode($list[0]));
    check($list[1]['result'] === 'unknown', json_encode($list[1]));
};

$tests['List: same key several times in a row, every read listed'] = function () {
    $list = RecentList::build(logWith([[9, 'c0ffee42', 'allowed', 'card'], [8, 'c0ffee42', 'allowed', 'card'], [7, 'c0ffee42', 'allowed', 'card']]), fn ($id) => null);
    check(array_column($list, 'time') === [9, 8, 7], json_encode($list));
};

$tests['List: entries of an earlier version without time and type'] = function () {
    $log = new DecisionLog(json_encode([
        ['ts' => '2026-10-16T19:30:00.000+02:00', 'id' => '1a2b3c4d5e6f7a8b', 'result' => 'allowed'],
        ['ts' => '2026-10-16T19:29:00.000+02:00', 'id' => 'c0ffee42', 'result' => 'unknown'],
    ]));
    $list = RecentList::build($log, fn ($id) => null);
    check($list[0]['time'] === strtotime('2026-10-16T19:30:00+02:00') && $list[0]['type'] === 'homekey', json_encode($list[0]));
    check($list[1]['type'] === 'card', json_encode($list[1]));
};
