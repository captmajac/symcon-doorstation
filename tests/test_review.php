<?php

// Findings of the final review

$tests['Review: read uses the value from the message, not the current variable value'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', 'Card');
    $first = json_encode(['ts' => date('Y-m-d\TH:i:s.vP', TestRegistry::$now), 'type' => 'card', 'id' => 'c0ffee42']);
    TestRegistry::$values[100] = json_encode(['ts' => date('Y-m-d\TH:i:s.vP', TestRegistry::$now), 'type' => 'card', 'id' => '0d15ea5e']);
    $m->MessageSink(TestRegistry::$now, 100, VM_UPDATE, [$first, true, '']);
    check(count(TestRegistry::$scriptRuns) === 1 && TestRegistry::$scriptRuns[0][1]['KEY_ID'] === 'c0ffee42', json_encode(TestRegistry::$scriptRuns));
};

$tests['Review: created key is watched without waiting for OM_CHILDADDED'] = function () {
    $m = makeAccess();
    readsOf($m, ['c0ffee42']);
    $m->RequestAction('CreateKey', json_encode(['id' => 'c0ffee42', 'name' => 'Card']));
    $e = IPS_GetObjectIDByIdent('key_c0ffee42', 2);
    check(in_array([$e, OM_CHANGENAME], $m->messages) && in_array([$e, EM_CHANGEACTIVE], $m->messages), json_encode($m->messages));
};

$tests['Review: tile initial data cannot open an HTML comment in the script'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', '<!--<script>');
    readLogged($m, 'c0ffee42');
    $tile = $m->GetVisualizationTile();
    $appended = substr($tile, strlen(file_get_contents(__DIR__ . '/../DoorstationAccess/module.html')));
    check(!str_contains($appended, '<!--'), $appended);
};

$tests['Review: busy semaphore drops the read with a log entry'] = function () {
    $m = makeAccess();
    addKey('c0ffee42', 'Card');
    TestRegistry::$semaphoreFree = false;
    read($m, 'c0ffee42');
    check(TestRegistry::$scriptRuns === [], 'opened');
    check(in_array('Read dropped: c0ffee42, instance busy', TestRegistry::$log), json_encode(TestRegistry::$log));
};

$tests['Review: no refresh before the kernel is ready'] = function () {
    TestRegistry::$runlevel = 10102;  // KR_INIT
    $m = makeAccess();
    check($m->visu === [], 'refreshed during startup');
    check(in_array([100, VM_UPDATE], $m->messages), 'VM_UPDATE missing');
    TestRegistry::$runlevel = KR_READY;
    $m->MessageSink(TestRegistry::$now, 0, KR_READY, []);
    check($m->visu !== [], 'no refresh after KR_READY');
};
