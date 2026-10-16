<?php

// Configuration: references to the MQTT variables and the door opener script

$tests['Config: without read variable status 201'] = function () {
    $m = makeAccess(['ReadVariable' => 0]);
    check($m->currentStatus() === 201, 'Status ' . $m->currentStatus());
};

$tests['Config: without script status 202'] = function () {
    check(makeAccess(['DoorScript' => 0])->currentStatus() === 202, 'not 202');
};

$tests['Config: read variable of type integer status 203'] = function () {
    TestRegistry::$variables[100] = ['VariableType' => 1, 'VariableUpdated' => 0];
    check(makeAccess()->currentStatus() === 203, 'not 203');
};

$tests['Config: complete status 102, references and VM_UPDATE registered'] = function () {
    $m = makeAccess(['StatusVariable' => 101]);
    check($m->currentStatus() === 102, 'not active');
    check(in_array([100, VM_UPDATE], $m->messages), 'VM_UPDATE missing');
    check(in_array([100, VM_DELETE], $m->messages), 'VM_DELETE read missing');
    check(in_array([101, VM_DELETE], $m->messages), 'VM_DELETE status missing');
    check(in_array([300, OM_UNREGISTER], $m->messages), 'OM_UNREGISTER missing');
    sort($m->references);
    check($m->references === [100, 101, 300], 'References ' . json_encode($m->references));
};

$tests['Config: ApplyChanges twice does not duplicate messages'] = function () {
    $m = makeAccess();
    $m->ApplyChanges();
    check(count(array_filter($m->messages, fn ($x) => $x === [100, VM_UPDATE])) === 1, json_encode($m->messages));
};

$tests['Config: tile visualization enabled'] = function () {
    check(makeAccess()->visualizationType === 1, 'no HTML-SDK tile');
};
