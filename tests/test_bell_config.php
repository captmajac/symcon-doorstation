<?php

// Bell module: configuration, instance status, objects below the instance

$tests['Bell config: required fields missing → 201'] = function () {
    foreach ([['RingVariable' => 0], ['CommandVariable' => 0], ['CallTarget' => ''], ['CallTarget' => '  '], ['RingVariable' => 999]] as $props) {
        TestRegistry::reset();
        check(makeBell($props)->currentStatus() === 201, json_encode($props));
    }
};

$tests['Bell config: error states 202 to 205'] = function () {
    check(makeBell(['MailFlapVariable' => 404])->currentStatus() === 204, 'only flap');
    TestRegistry::reset();
    check(makeBell(['MailDoorVariable' => 405])->currentStatus() === 204, 'only door');
    TestRegistry::reset();
    check(makeBell(['MotionVariable' => 999])->currentStatus() === 205, 'missing motion variable');
    TestRegistry::reset();
    check(makeBell(['RingScript' => 999])->currentStatus() === 202, 'missing script');
    TestRegistry::reset();
    TestRegistry::$variables[401] = ['VariableType' => 1, 'VariableUpdated' => 0];
    TestRegistry::$values[401] = 0;
    check(makeBell()->currentStatus() === 203, 'command not string');
};

$tests['Bell config: valid → 102, messages on the selected variables'] = function () {
    $m = makeBell(['MotionVariable' => 403, 'NotifyScript' => 502]);
    check($m->currentStatus() === 102, (string) $m->currentStatus());
    foreach ([[400, VM_UPDATE], [400, VM_DELETE], [401, VM_DELETE], [403, VM_UPDATE], [502, OM_UNREGISTER]] as $msg) {
        check(in_array($msg, $m->messages, true), json_encode($msg));
    }
    check(!in_array([404, VM_UPDATE], $m->messages, true), 'flap without selection');
};

$tests['Bell config: objects without optional variables'] = function () {
    $m = makeBell();
    check(array_keys($m->variables) === ['Ringtone', 'Rings'], json_encode(array_keys($m->variables)));
    check($m->positions['Ringtone'] === 10, 'Ringtone position');
    check(in_array('Ringtone', $m->actions, true), 'Ringtone action');
    check(enumCaptions($m->presentations['Ringtone']) === ['false' => 'Muted', 'true' => 'On'], json_encode(enumCaptions($m->presentations['Ringtone'])));
};

$tests['Bell config: objects with motion and mailbox, removed again'] = function () {
    $m = makeBell(['MotionVariable' => 403, 'MailFlapVariable' => 404, 'MailDoorVariable' => 405]);
    check(array_keys($m->variables) === ['Ringtone', 'Rings', 'Motion', 'LastMotion', 'Mailbox'], json_encode(array_keys($m->variables)));
    check($m->positions['Motion'] === 30 && $m->presentations['Motion'] === '~Motion', 'Motion');
    check($m->positions['LastMotion'] === 40 && $m->presentations['LastMotion'] === '~UnixTimestamp', 'LastMotion');
    check($m->positions['Mailbox'] === 50 && in_array('Mailbox', $m->actions, true), 'Mailbox');
    check(enumCaptions($m->presentations['Mailbox']) === ['0' => 'Empty', '1' => 'Mail', '2' => 'Newspaper'], json_encode(enumCaptions($m->presentations['Mailbox'])));
    $m->properties['MotionVariable'] = 0;
    $m->ApplyChanges();
    check(array_keys($m->variables) === ['Ringtone', 'Rings', 'Mailbox'], json_encode(array_keys($m->variables)));
};

$tests['Bell config: timers and tile'] = function () {
    $m = makeBell();
    foreach (['Notify', 'ConfirmTimeout', 'Midnight'] as $timer) {
        check(isset($m->timers[$timer]), $timer);
    }
    check($m->visualizationType === 1, 'visualization type');
    check(str_contains($m->GetConfigurationForm(), 'CallTarget'), 'form');
};

$tests['Bell config: selected variable deleted → error status'] = function () {
    $m = makeBell();
    unset(TestRegistry::$variables[400]);
    $m->MessageSink(TestRegistry::$now, 400, VM_DELETE, []);
    check($m->currentStatus() === 201, (string) $m->currentStatus());
};

$tests['Bell config: form has each script next to its sensor'] = function () {
    $rows = [];
    foreach (json_decode(file_get_contents(__DIR__ . '/../DoorstationBell/form.json'), true)['elements'] as $element) {
        $rows[] = array_column($element['type'] === 'RowLayout' ? $element['items'] : [$element], 'name');
    }
    check($rows === [
        ['RingVariable', 'RingScript', 'HistoryLength'],
        ['CommandVariable', 'CallTarget', 'TargetVariable'],
        ['MotionVariable', 'MotionScript'],
        ['MailFlapVariable', 'MailDoorVariable'],
        ['NotifyScript', 'NotifyDelay'],
    ], json_encode($rows));
};
