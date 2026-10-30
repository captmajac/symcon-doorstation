<?php

// Ring log: last 20 rings, newest first

$tests['RingLog: keeps 20, oldest drops out'] = function () {
    $log = new RingLog('[]');
    for ($i = 1; $i <= 21; $i++) {
        $log->add($i, false);
    }
    $all = $log->latest(50);
    check(count($all) === 20, (string) count($all));
    check($all[0]['time'] === 21 && $all[19]['time'] === 2, json_encode([$all[0], $all[19]]));
};

$tests['RingLog: latest n newest first'] = function () {
    $log = new RingLog('[]');
    foreach ([10, 20, 30, 40] as $t) {
        $log->add($t, $t === 30);
    }
    check($log->latest(3) === [['time' => 40, 'muted' => false], ['time' => 30, 'muted' => true], ['time' => 20, 'muted' => false]], json_encode($log->latest(3)));
};

$tests['RingLog: broken JSON is empty'] = function () {
    check((new RingLog('kaputt'))->latest(3) === [], 'not empty');
};

$tests['RingLog: round trip keeps muted'] = function () {
    $log = new RingLog('[]');
    $log->add(5, true);
    check((new RingLog($log->toJson()))->latest(1) === [['time' => 5, 'muted' => true]], $log->toJson());
};
