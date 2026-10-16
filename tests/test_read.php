<?php

// Handling a read: decision, door opener script, decision log

function decisions(DoorstationAccess $m): array { return json_decode($m->attributes['Decisions'], true); }
function lockedAllDay(): array
{
    return [['ID' => 0, 'Days' => 127, 'Points' => [['ID' => 0, 'Start' => ['Hour' => 0, 'Minute' => 0, 'Second' => 0], 'ActionID' => 2]]]];
}

$tests['Read: known key, schedule open, script gets KEY_ID, KEY_NAME, KEY_TYPE, TS'] = function () {
    $m = makeAccess();
    addKey('1a2b3c4d5e6f7a8b', 'Alex iPhone');
    read($m, '1a2b3c4d5e6f7a8b', 1, 'homekey');
    $ts = json_decode(GetValue(100), true)['ts'];
    check(TestRegistry::$scriptRuns === [[300, ['KEY_ID' => '1a2b3c4d5e6f7a8b', 'KEY_NAME' => 'Alex iPhone', 'KEY_TYPE' => 'homekey', 'TS' => $ts]]], json_encode(TestRegistry::$scriptRuns));
    check(decisions($m)[0] === ['ts' => $ts, 'id' => '1a2b3c4d5e6f7a8b', 'result' => 'allowed'], json_encode(decisions($m)));
    check(in_array('Door opener started for Alex iPhone', TestRegistry::$log), json_encode(TestRegistry::$log));
};

$tests['Read: upper-case ID matches lower-case ident'] = function () {
    $m = makeAccess();
    addKey('04a1b2c3d4e5f6', 'AirTag');
    read($m, '04A1B2C3D4E5F6');
    check(count(TestRegistry::$scriptRuns) === 1, 'not opened');
};

$tests['Read: unknown key, no script, decision unknown'] = function () {
    $m = makeAccess();
    read($m, 'c0ffee42');
    check(TestRegistry::$scriptRuns === [], 'opened');
    check(decisions($m)[0]['result'] === 'unknown', json_encode(decisions($m)));
    check(in_array('Unknown key c0ffee42', TestRegistry::$log), json_encode(TestRegistry::$log));
};

$tests['Read: inactive and outside schedule do not open'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', 'Card', false);
    addKey('0d15ea5e', 'Card 2', true, lockedAllDay());
    read($m, 'c0ffee42');
    read($m, '0d15ea5e');
    check(TestRegistry::$scriptRuns === [], 'opened');
    check(array_column(decisions($m), 'result') === ['outside_schedule', 'inactive'], json_encode(decisions($m)));
    check(in_array('Key Card is inactive', TestRegistry::$log) && in_array('Key Card 2 is outside its schedule', TestRegistry::$log), json_encode(TestRegistry::$log));
};

$tests['Read: 6 s old is stale, no script'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', 'Card');
    read($m, 'c0ffee42', 6);
    check(TestRegistry::$scriptRuns === [], 'opened');
    check(decisions($m)[0]['result'] === 'stale', json_encode(decisions($m)));
    check(in_array('Read discarded: c0ffee42 is too old', TestRegistry::$log), json_encode(TestRegistry::$log));
};

$tests['Read: same ts and id twice runs the script once'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', 'Card');
    read($m, 'c0ffee42');
    $m->MessageSink(TestRegistry::$now, 100, VM_UPDATE, [GetValue(100), false, GetValue(100)]);
    check(count(TestRegistry::$scriptRuns) === 1, 'ran ' . count(TestRegistry::$scriptRuns));
    check(count(decisions($m)) === 1, 'logged twice');
};

$tests['Read: no JSON, no script, no decision'] = function () {
    $m = makeAccess();
    TestRegistry::$values[100] = '04A1B2C3D4E5F6';
    $m->MessageSink(TestRegistry::$now, 100, VM_UPDATE, ['04A1B2C3D4E5F6', true, '']);
    check(TestRegistry::$scriptRuns === [] && decisions($m) === [], 'reacted');
    check(in_array('Read ignored: invalid_json, value: 04A1B2C3D4E5F6', TestRegistry::$log), json_encode(TestRegistry::$log));
};

$tests['Read: ignored value is logged in full'] = function () {
    $m = makeAccess();
    $raw = '{"ts":"2026-10-16T19:22:02.123+02:00","type":"homekey","id":"' . str_repeat('ab', 32) . '"';
    $m->MessageSink(TestRegistry::$now, 100, VM_UPDATE, [$raw, true, '']);
    check(in_array("Read ignored: invalid_json, value: $raw", TestRegistry::$log), json_encode(TestRegistry::$log));
};

$tests['Read: status 201 ignores reads'] = function () {
    $m = makeAccess(['ReadVariable' => 0]);
    addKey('c0ffee42', 'Card');
    read($m, 'c0ffee42');
    check(TestRegistry::$scriptRuns === [], 'opened');
};

$tests['Deleted: VM_DELETE of read variable status 201, OM_UNREGISTER of script status 202'] = function () {
    $m = makeAccess();
    unset(TestRegistry::$variables[100]);
    $m->MessageSink(TestRegistry::$now, 100, VM_DELETE, []);
    check($m->currentStatus() === 201, 'status ' . $m->currentStatus());
    $m = makeAccess();
    TestRegistry::$scripts = [];
    $m->MessageSink(TestRegistry::$now, 300, OM_UNREGISTER, []);
    check($m->currentStatus() === 202, 'status ' . $m->currentStatus());
};

$tests['Restart: KR_READY re-registers, does not open with a fresh read in the variable'] = function () {
    TestRegistry::$runlevel = 10102;  // KR_INIT
    $m = makeAccess();
    check(in_array([0, KR_READY], $m->messages), 'KR_READY not registered');
    addKey('c0ffee42', 'Card');
    TestRegistry::$values[100] = json_encode(['ts' => date('Y-m-d\TH:i:s.vP', TestRegistry::$now), 'type' => 'card', 'id' => 'c0ffee42']);
    TestRegistry::$runlevel = KR_READY;
    $m->MessageSink(TestRegistry::$now, 0, KR_READY, []);
    check(TestRegistry::$scriptRuns === [], 'opened on restart');
    check(in_array([100, VM_UPDATE], $m->messages), 'VM_UPDATE missing');
};

$tests['Read: allowed without script logs, does not run anything'] = function () {
    $m = makeAccess(['DoorScript' => 0]);
    addKey('c0ffee42', 'Card');
    read($m, 'c0ffee42');
    check(TestRegistry::$scriptRuns === [], 'ran');
    check(in_array('Access granted for Card, no door opener script', TestRegistry::$log), json_encode(TestRegistry::$log));
    check(decisions($m)[0]['result'] === 'allowed', json_encode(decisions($m)));
};
