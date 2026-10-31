<?php

// Bell module: motion, last motion, script on motion

function motionBell(): TestBell
{
    return makeBell(['MotionVariable' => 403, 'MotionScript' => 501]);
}

$tests['Bell motion: idle → motion sets variables and runs the script'] = function () {
    $m = motionBell();
    update($m, 403, 'motion');
    check($m->value('Motion') === true && $m->value('LastMotion') === TestRegistry::$now, json_encode([$m->value('Motion'), $m->value('LastMotion')]));
    check(TestRegistry::$scriptRuns === [[501, ['TS' => TestRegistry::$now]]], json_encode(TestRegistry::$scriptRuns));
    check(lastPayload($m)['motion'] === true && lastPayload($m)['last_motion'] === TestRegistry::$now, json_encode(lastPayload($m)));
};

$tests['Bell motion: same state delivered again → nothing'] = function () {
    $m = motionBell();
    update($m, 403, 'motion');
    $first = TestRegistry::$now;
    TestRegistry::$now += 60;
    update($m, 403, 'motion');
    check(count(TestRegistry::$scriptRuns) === 1 && $m->value('LastMotion') === $first, json_encode(TestRegistry::$scriptRuns));
};

$tests['Bell motion: motion → idle without script'] = function () {
    $m = motionBell();
    update($m, 403, 'motion');
    update($m, 403, 'idle');
    check($m->value('Motion') === false && count(TestRegistry::$scriptRuns) === 1, json_encode(TestRegistry::$scriptRuns));
};

$tests['Bell motion: JSON with new ts but same state → nothing'] = function () {
    TestRegistry::$values[403] = '{"ts":"2026-10-16T18:00:00+02:00","state":"motion"}';
    $m = motionBell();
    update($m, 403, '{"ts":"2026-10-16T18:05:00+02:00","state":"motion"}');
    check(TestRegistry::$scriptRuns === [], json_encode(TestRegistry::$scriptRuns));
};

$tests['Bell motion: unknown value is logged and ignored'] = function () {
    $m = motionBell();
    update($m, 403, 'xyz');
    check($m->value('Motion') === false && TestRegistry::$scriptRuns === [], 'changed');
    check(count(array_filter(TestRegistry::$log, fn ($l) => str_contains($l, 'xyz'))) === 1, json_encode(TestRegistry::$log));
};

$tests['Bell motion: without script only the variables'] = function () {
    $m = makeBell(['MotionVariable' => 403]);
    update($m, 403, 'motion');
    check($m->value('Motion') === true && TestRegistry::$scriptRuns === [], 'script ran');
};

$tests['Bell motion: no motion yet → last_motion null'] = function () {
    $m = motionBell();
    check(lastPayload($m)['motion'] === false && lastPayload($m)['last_motion'] === null, json_encode(lastPayload($m)));
};
