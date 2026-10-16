<?php

// The stub itself behaves like Symcon where the module relies on it

$tests['Stub: create event, find by ident, read schedule'] = function () {
    $id = IPS_CreateEvent(2);
    IPS_SetParent($id, 2);
    IPS_SetIdent($id, 'key_ab12cd34');
    IPS_SetName($id, 'Card');
    IPS_SetEventScheduleAction($id, 1, 'Access', 0x2E7D32, '');
    IPS_SetEventScheduleGroup($id, 0, 127);
    IPS_SetEventScheduleGroupPoint($id, 0, 0, 0, 0, 0, 1);
    IPS_SetEventActive($id, true);
    check(IPS_GetObjectIDByIdent('key_ab12cd34', 2) === $id, 'ident not found');
    check(IPS_GetObjectIDByIdent('key_ab12cd34', 3) === false, 'found under other parent');
    check(IPS_GetChildrenIDs(2) === [$id], 'children');
    check(IPS_GetObject($id)['ObjectName'] === 'Card' && IPS_GetObject($id)['ObjectType'] === 4, 'object');
    $e = IPS_GetEvent($id);
    check($e['EventActive'] === true, 'not active');
    check($e['ScheduleActions'][0] === ['ID' => 1, 'Name' => 'Access', 'Color' => 0x2E7D32], json_encode($e['ScheduleActions']));
    check($e['ScheduleGroups'][0]['Days'] === 127, 'days');
    check($e['ScheduleGroups'][0]['Points'][0] === ['ID' => 0, 'Start' => ['Hour' => 0, 'Minute' => 0, 'Second' => 0], 'ActionID' => 1], 'point');
    IPS_DeleteEvent($id);
    check(!IPS_EventExists($id) && IPS_GetChildrenIDs(2) === [], 'not deleted');
};

$tests['Stub: archive newest first with limit, logging status'] = function () {
    TestRegistry::$archiveID = 50;
    TestRegistry::$archive[100] = [['TimeStamp' => 3, 'Value' => 'c'], ['TimeStamp' => 2, 'Value' => 'b'], ['TimeStamp' => 1, 'Value' => 'a']];
    TestRegistry::$logging[100] = true;
    check(IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}') === [50], 'archive instance');
    check(AC_GetLoggingStatus(50, 100) === true && AC_GetLoggingStatus(50, 101) === false, 'logging');
    check(array_column(AC_GetLoggedValues(50, 100, 0, 0, 2), 'Value') === ['c', 'b'], 'limit');
};

$tests['Stub: helpers addKey and read'] = function () {
    $m = makeAccess();
    $id = addKey('c0ffee42', 'Card');
    check(IPS_GetEvent($id)['EventActive'] === true && IPS_GetObject($id)['ObjectIdent'] === 'key_c0ffee42', 'addKey');
    read($m, 'C0FFEE42', 2);
    $data = json_decode(GetValue(100), true);
    check($data['id'] === 'C0FFEE42' && (new DateTimeImmutable($data['ts']))->getTimestamp() === TestRegistry::$now - 2, 'read');
    check(TestRegistry::$variables[100]['VariableUpdated'] === TestRegistry::$now, 'updated');
};

$tests['Stub: script run recorded'] = function () {
    IPS_RunScriptEx(300, ['A' => 'b']);
    check(TestRegistry::$scriptRuns === [[300, ['A' => 'b']]], json_encode(TestRegistry::$scriptRuns));
};
