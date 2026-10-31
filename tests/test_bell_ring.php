<?php

// Bell module: rings, ring history, HTML box, midnight

// Ring 3 s after the previous one (the doorstation sends rings at least 2 s apart)
function ring(DoorstationBell $m, string $value = 'bell'): void
{
    TestRegistry::$now += 3;
    update($m, 400, $value);
}

$tests['Bell ring: same value twice → two entries, script twice'] = function () {
    $m = makeBell(['RingScript' => 500]);
    ring($m);
    ring($m);
    $rings = lastPayload($m)['rings'];
    check(count($rings) === 2 && $rings[0] === ['time' => TestRegistry::$now, 'muted' => false], json_encode($rings));
    check(TestRegistry::$scriptRuns === [
        [500, ['TS' => TestRegistry::$now - 3, 'MUTED' => false]],
        [500, ['TS' => TestRegistry::$now, 'MUTED' => false]],
    ], json_encode(TestRegistry::$scriptRuns));
};

$tests['Bell ring: without script only the entry'] = function () {
    $m = makeBell();
    ring($m);
    check(count(lastPayload($m)['rings']) === 1, 'no entry');
    check(TestRegistry::$scriptRuns === [], json_encode(TestRegistry::$scriptRuns));
};

$tests['Bell ring: muted ring is marked, script gets MUTED'] = function () {
    $m = makeBell(['RingScript' => 500]);
    TestRegistry::$values[$m->variables['Ringtone']] = false;
    ring($m);
    check(lastPayload($m)['rings'][0]['muted'] === true, json_encode(lastPayload($m)));
    check(TestRegistry::$scriptRuns[0][1]['MUTED'] === true, json_encode(TestRegistry::$scriptRuns));
};

$tests['Bell ring: history length 3, HTML box with today and muted'] = function () {
    $m = makeBell(['HistoryLength' => 3]);
    for ($i = 0; $i < 4; $i++) {
        ring($m);
    }
    TestRegistry::$values[$m->variables['Ringtone']] = false;
    ring($m);
    $rings = lastPayload($m)['rings'];
    check(count($rings) === 3 && $rings[0]['time'] > $rings[1]['time'], json_encode($rings));
    $html = $m->value('Rings');
    check(substr_count($html, '<tr') === 3, $html);
    check(str_contains($html, 'Today 18:08') && substr_count($html, 'muted') === 1, $html);
};

$tests['Bell ring: default history length 3'] = function () {
    $m = makeBell();
    for ($i = 0; $i < 5; $i++) {
        ring($m);
    }
    check(count(lastPayload($m)['rings']) === 3, (string) count(lastPayload($m)['rings']));
};

$tests['Bell ring: payload without motion and mailbox'] = function () {
    $m = makeBell();
    ring($m);
    $p = lastPayload($m);
    check(array_keys($p) === ['muted', 'pending', 'motion', 'last_motion', 'mailbox', 'rings', 'ring_var', 'motion_var'], json_encode(array_keys($p)));
    check($p['motion'] === null && $p['last_motion'] === null && $p['mailbox'] === null && $p['muted'] === false && $p['pending'] === false, json_encode($p));
};

$tests['Bell ring: midnight timer and refresh'] = function () {
    $m = makeBell();
    $midnight = mktime(0, 0, 0, 10, 17, 2026);
    check(($m->timers['Midnight']['interval'] ?? 0) === ($midnight - TestRegistry::$now + 1) * 1000, json_encode($m->timers['Midnight']));
    $before = count($m->visu);
    $m->RequestAction('Midnight', '');
    check(count($m->visu) === $before + 1, 'no refresh');
};

$tests['Bell ring: tile gets the current payload'] = function () {
    $m = makeBell();
    ring($m);
    check(str_contains($m->GetVisualizationTile(), 'handleMessage('), 'no initial payload');
};

$tests['Bell ring: no evaluation in error status'] = function () {
    $m = makeBell(['CallTarget' => '']);
    ring($m);
    check(lastPayload($m) === [] && TestRegistry::$scriptRuns === [], json_encode($m->visu));
};

$tests['Bell ring: second update within 2 s is the same ring'] = function () {
    $m = makeBell(['RingScript' => 500]);
    ring($m);
    update($m, 400, 'bell');            // same second
    TestRegistry::$now += 1;
    update($m, 400, 'bell');            // 1 s later
    check(count(lastPayload($m)['rings']) === 1 && count(TestRegistry::$scriptRuns) === 1, json_encode(lastPayload($m)['rings']));
    TestRegistry::$now += 2;            // 3 s after the first: a new ring
    update($m, 400, 'bell');
    check(count(lastPayload($m)['rings']) === 2 && count(TestRegistry::$scriptRuns) === 2, json_encode(lastPayload($m)['rings']));
};

$tests['Bell ring: payload names the variables for the charts'] = function () {
    $m = makeBell(['MotionVariable' => 403]);
    ring($m);
    $p = lastPayload($m);
    check($p['ring_var'] === 400 && $p['motion_var'] === $m->variables['Motion'], json_encode($p));
    TestRegistry::reset();
    $m = makeBell();
    ring($m);
    check(lastPayload($m)['motion_var'] === null, json_encode(lastPayload($m)));
};
