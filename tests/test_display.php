<?php

// HTML box (old WebFront), tile data, configuration form

$tests['Display: payload after two reads, newest allowed with name, older unknown'] = function () {
    $m = makeAccess();
    addKey('1a2b3c4d5e6f7a8b', 'Alex iPhone');
    readLogged($m, 'c0ffee42');
    readLogged($m, '1a2b3c4d5e6f7a8b', 'homekey');
    $p = lastPayload($m);
    check(array_keys($p) === ['status', 'archive_ok', 'recent', 'keys'], json_encode(array_keys($p)));
    check($p['archive_ok'] === true, 'archive_ok');
    check($p['recent'][0] === ['time' => TestRegistry::$now, 'id' => '1a2b3c4d5e6f7a8b', 'name' => 'Alex iPhone', 'type' => 'homekey', 'result' => 'allowed'], json_encode($p['recent'][0]));
    check($p['recent'][1]['name'] === null && $p['recent'][1]['result'] === 'unknown', json_encode($p['recent'][1]));
};

$tests['Display: keys with event ID, active, allowed_now'] = function () {
    $m = makeAccess();
    $a = addKey('c0ffee42', 'Card');
    $b = addKey('0d15ea5e', 'Card 2', false);
    readLogged($m, 'c0ffee42');
    $keys = lastPayload($m)['keys'];
    usort($keys, fn ($x, $y) => strcmp($x['name'], $y['name']));
    check($keys === [
        ['id' => 'c0ffee42', 'name' => 'Card', 'event' => $a, 'active' => true, 'allowed_now' => true],
        ['id' => '0d15ea5e', 'name' => 'Card 2', 'event' => $b, 'active' => false, 'allowed_now' => false],
    ], json_encode($keys));
};

$tests['Display: status null without status variable, degraded with'] = function () {
    $m = makeAccess();
    readLogged($m, 'c0ffee42');
    check(lastPayload($m)['status'] === null, 'not null');
    TestRegistry::$values[101] = 'degraded';
    $m = makeAccess(['StatusVariable' => 101]);
    $m->MessageSink(TestRegistry::$now, 101, VM_UPDATE, ['degraded', true, 'online']);
    check(lastPayload($m)['status'] === 'degraded', json_encode(lastPayload($m)));
    check(!str_contains($m->value('Recent'), 'degraded'), 'status in HTML box: ' . $m->value('Recent'));
};

$tests['Display: reader status as own variable with profile'] = function () {
    $m = makeAccess(['StatusVariable' => 101]);
    check($m->value('Reader') === 2, 'online: ' . json_encode($m->value('Reader')));
    check(array_keys(TestRegistry::$profiles['DSA.ReaderStatus']['associations'] ?? []) === [0, 1, 2], 'profile');
    foreach (['degraded' => 1, 'offline' => 0, ' online ' => 2, 'garbage' => 0] as $text => $expected) {
        TestRegistry::$values[101] = $text;
        $m->MessageSink(TestRegistry::$now, 101, VM_UPDATE, [$text, true, '']);
        check($m->value('Reader') === $expected, "$text: " . json_encode($m->value('Reader')));
    }
    $m->properties['StatusVariable'] = 0;
    $m->ApplyChanges();
    check(!isset($m->variables['Reader']), 'Reader kept');
};

$tests['Display: archiving off, recent empty, hint in form and HTML box'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', 'Card');
    read($m, 'c0ffee42');
    $p = lastPayload($m);
    check($p['archive_ok'] === false && $p['recent'] === [], json_encode($p));
    $hint = 'The read variable is not archived. Enable archiving to see recent accesses.';
    check(str_contains($m->GetConfigurationForm(), $hint), 'form');
    check(str_contains($m->value('Recent'), $hint), 'HTML box');
    TestRegistry::$archiveID = 50;
    TestRegistry::$logging[100] = true;
    check(!str_contains($m->GetConfigurationForm(), $hint), 'hint with archiving');
};

$tests['Display: HTML box escapes names'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', '<b>Guest</b> & "x"');
    readLogged($m, 'c0ffee42');
    $html = $m->value('Recent');
    check(!str_contains($html, '<b>Guest'), 'not escaped: ' . $html);
    check(str_contains($html, '&lt;b&gt;Guest&lt;/b&gt; &amp; &quot;x&quot;'), $html);
};

$tests['Display: HTML box colors by result'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', 'Card');
    archiveReads(['0d15ea5e']);  // no decision known
    readLogged($m, '04a1b2c3d4e5f6');
    readLogged($m, 'c0ffee42');
    $html = $m->value('Recent');
    check(str_contains($html, '<td style="color:#2E7D32">Card</td>'), 'green cell: ' . $html);
    check(preg_match('/<tr title="unknown"><td style="color:#C62828">/', $html) === 1, 'red: ' . $html);
    check(str_contains($html, '<tr><td>'), 'neutral row missing: ' . $html);
    check(str_contains($html, 'Unknown (04a1b2c3d4e5f6)'), 'unknown name');
    check(str_contains($html, date('d.m.Y H:i', TestRegistry::$now)), 'date');
};

$tests['Display: renaming the event updates HTML box and tile'] = function () {
    $m = makeAccess();
    $e = addKey('c0ffee42', 'Card');
    $m->ApplyChanges();
    check(in_array([$e, OM_CHANGENAME], $m->messages) && in_array([$e, EM_CHANGEACTIVE], $m->messages), 'not registered');
    readLogged($m, 'c0ffee42');
    IPS_SetName($e, 'Garden');
    $m->MessageSink(TestRegistry::$now, $e, OM_CHANGENAME, ['Garden']);
    check(lastPayload($m)['recent'][0]['name'] === 'Garden', json_encode(lastPayload($m)));
    check(str_contains($m->value('Recent'), 'Garden'), 'HTML box');
};

$tests['Display: new child event registered, removed key unknown on next read'] = function () {
    archiveReads(['c0ffee42']);
    $m = makeAccess();
    check(in_array([2, OM_CHILDADDED], $m->messages) && in_array([2, OM_CHILDREMOVED], $m->messages), 'instance messages');
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => 'Card']));
    $e = IPS_GetObjectIDByIdent('key_c0ffee42', 2);
    $m->MessageSink(TestRegistry::$now, 2, OM_CHILDADDED, [$e]);
    check(in_array([$e, OM_CHANGENAME], $m->messages), 'child not registered');
    IPS_DeleteEvent($e);
    $m->MessageSink(TestRegistry::$now, 2, OM_CHILDREMOVED, [$e]);
    readLogged($m, 'c0ffee42');
    check(lastPayload($m)['recent'][0]['result'] === 'unknown', json_encode(lastPayload($m)));
};

$tests['Display: GetVisualizationTile contains handleMessage and the data'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', 'Card garden');
    readLogged($m, 'c0ffee42');
    $tile = $m->GetVisualizationTile();
    check(str_contains($tile, 'handleMessage('), 'no handleMessage');
    check(str_contains($tile, 'Card garden'), 'no data');
    check(!str_contains($tile, '</script><'), 'payload breaks out of script');
};

$tests['Tile: name entry per row, not per ID (same unknown key twice in the list)'] = function () {
    $html = file_get_contents(__DIR__ . '/../DoorstationAccess/module.html');
    check(str_contains($html, "editing = 'recent:' + index"), 'recent row not by index');
    check(!str_contains($html, 'editing = entry.id') && !str_contains($html, 'editing = key.id'), 'still by ID');
};

$tests['Tile: module.html never puts data into innerHTML, uses the SDK calls'] = function () {
    $html = file_get_contents(__DIR__ . '/../DoorstationAccess/module.html');
    preg_match_all('/innerHTML\s*=\s*([^;]*)/', $html, $m);
    foreach ($m[1] as $value) {
        check(trim($value) === "''", 'innerHTML = ' . $value);
    }
    foreach (['function handleMessage', "requestAction('CreateKey'", "requestAction('RenameKey'", "requestAction('SetKeyActive'", "requestAction('DeleteKey'", 'openObject(', 'textContent'] as $needle) {
        check(str_contains($html, $needle), "missing $needle");
    }
};
