<?php

// Key management from the tile (RequestAction)

$tests['Keys: CreateKey for archived ID creates the weekly schedule'] = function () {
    archiveReads(['c0ffee42']);
    $m = makeAccess();
    $m->RequestAction('CreateKey', json_encode(['id' => 'C0FFEE42', 'name' => 'Card garden']));
    $e = IPS_GetObjectIDByIdent('key_c0ffee42', 2);
    check($e !== false, 'no event');
    check(IPS_GetObject($e)['ObjectName'] === 'Card garden', 'name');
    $ev = IPS_GetEvent($e);
    check($ev['EventType'] === 2 && $ev['EventActive'] === true, 'type/active');
    check($ev['ScheduleActions'] === [['ID' => 1, 'Name' => 'Access', 'Color' => 0x2E7D32], ['ID' => 2, 'Name' => 'Locked', 'Color' => 0xC62828]], json_encode($ev['ScheduleActions']));
    $day = fn ($i) => ['ID' => $i, 'Days' => 1 << $i, 'Points' => [['ID' => 0, 'Start' => ['Hour' => 0, 'Minute' => 0, 'Second' => 0], 'ActionID' => 1]]];
    check(array_values($ev['ScheduleGroups']) === array_map($day, range(0, 6)), json_encode($ev['ScheduleGroups']));
    check(lastError($m) === null, lastError($m) ?? '');
};

$tests['Keys: schedule is complete before it appears under the instance (visualization shows it at once)'] = function () {
    archiveReads(['c0ffee42']);
    $m = makeAccess();
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => 'Card']));
    $e = IPS_GetObjectIDByIdent('key_c0ffee42', 2);
    $atParent = TestRegistry::$parented[$e] ?? [];
    check(count($atParent['ScheduleGroups'] ?? []) === 7 && count($atParent['ScheduleActions'] ?? []) === 2 && ($atParent['EventActive'] ?? false), json_encode($atParent));
};

$tests['Keys: created key opens on next read'] = function () {
    archiveReads(['c0ffee42']);
    $m = makeAccess();
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => 'Card']));
    read($m, 'c0ffee42');
    check(count(TestRegistry::$scriptRuns) === 1, 'not opened');
};

$tests['Keys: CreateKey for ID outside the last 5 reads is refused'] = function () {
    archiveReads(['11111111', '22222222', '33333333', '44444444', '55555555', 'c0ffee42']);
    $m = makeAccess();
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => 'Card']));
    check(IPS_GetObjectIDByIdent('key_c0ffee42', 2) === false, 'created');
    check(lastError($m) === 'Key not in recent reads', (string) lastError($m));
};

$tests['Keys: CreateKey for an existing key is refused'] = function () {
    archiveReads(['c0ffee42']);
    $m = makeAccess();
    addKey('c0ffee42', 'Card');
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => 'Other']));
    check(lastError($m) === 'Key already exists', (string) lastError($m));
    check(count(IPS_GetChildrenIDs(2)) === 1, 'second event');
};

$tests['Keys: name blank or 41 characters refused, surrounding spaces trimmed'] = function () {
    archiveReads(['c0ffee42']);
    $m = makeAccess();
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => '   ']));
    check(lastError($m) === 'Name must be 1 to 40 characters', 'blank: ' . lastError($m));
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => str_repeat('ä', 41)]));
    check(lastError($m) === 'Name must be 1 to 40 characters', '41: ' . lastError($m));
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => str_repeat('ä', 40)]));
    check(IPS_GetObjectIDByIdent('key_c0ffee42', 2) !== false, '40 multibyte refused');
    $m->RequestAction('RenameKey', json_encode(['id' => 'c0ffee42', 'name' => '  Alex  ']));
    check(IPS_GetObject(IPS_GetObjectIDByIdent('key_c0ffee42', 2))['ObjectName'] === 'Alex', 'not trimmed');
};

$tests['Keys: RenameKey, SetKeyActive and DeleteKey act on the event'] = function () {
    $m = makeAccess();
    $e = addKey('c0ffee42', 'Card');
    $m->RequestAction('RenameKey', json_encode(['id' => 'c0ffee42', 'name' => 'Guest']));
    check(IPS_GetObject($e)['ObjectName'] === 'Guest', 'rename');
    $m->RequestAction('SetKeyActive', json_encode(['id' => 'c0ffee42', 'active' => false]));
    check(IPS_GetEvent($e)['EventActive'] === false, 'inactive');
    $m->RequestAction('DeleteKey', json_encode(['id' => 'c0ffee42']));
    check(!IPS_EventExists($e), 'not deleted');
};

$tests['Keys: RenameKey for unknown ID refused'] = function () {
    $m = makeAccess();
    $m->RequestAction('RenameKey', json_encode(['id' => 'c0ffee42', 'name' => 'Guest']));
    check(lastError($m) === 'Unknown key', (string) lastError($m));
};

$tests['Keys: invalid JSON or ID refused'] = function () {
    $m = makeAccess();
    $m->RequestAction('DeleteKey', 'nonsense');
    check(lastError($m) === 'Invalid key', 'json: ' . lastError($m));
    $m->RequestAction('DeleteKey', json_encode(['id' => '../x']));
    check(lastError($m) === 'Invalid key', 'id: ' . lastError($m));
    $m->RequestAction('SetKeyActive', json_encode(['id' => 'c0ffee42', 'active' => 'yes']));
    check(lastError($m) === 'Invalid key', 'active: ' . lastError($m));
};
