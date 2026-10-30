<?php

// Bell module: ringtone muted/on via the call target of the doorstation

const MUTE_CMD = '{"action":"set_target","target":null}';
const ON_CMD = '{"action":"set_target","target":"**701"}';

$tests['Bell mute: without feedback the switch shows the sent value at once'] = function () {
    $m = makeBell();
    TestRegistry::$requests = [];  // call target of the first configuration
    $m->RequestAction('SetMuted', 'true');
    check(TestRegistry::$requests === [[401, MUTE_CMD]], json_encode(TestRegistry::$requests));
    check($m->value('Ringtone') === false && lastPayload($m)['pending'] === false && lastPayload($m)['muted'] === true, json_encode(lastPayload($m)));
    $m->RequestAction('Ringtone', true);
    check(TestRegistry::$requests[1] === [401, ON_CMD], json_encode(TestRegistry::$requests));
    check($m->value('Ringtone') === true, 'not on');
};

$tests['Bell mute: with feedback the switch waits for the doorstation'] = function () {
    $m = makeBell(['TargetVariable' => 402]);
    $m->RequestAction('SetMuted', 'true');
    check(TestRegistry::$requests === [[401, MUTE_CMD]], json_encode(TestRegistry::$requests));
    check($m->value('Ringtone') === true && lastPayload($m)['pending'] === true, json_encode(lastPayload($m)));
    check($m->timers['ConfirmTimeout']['interval'] === 10000, json_encode($m->timers['ConfirmTimeout']));
    update($m, 402, '');
    check($m->value('Ringtone') === false && lastPayload($m)['pending'] === false, json_encode(lastPayload($m)));
    check($m->timers['ConfirmTimeout']['interval'] === 0, 'timer still running');
};

$tests['Bell mute: no confirmation within 10 s → back to the reported state, log'] = function () {
    $m = makeBell(['TargetVariable' => 402]);
    $m->RequestAction('SetMuted', 'true');
    $m->RequestAction('ConfirmTimeout', '');
    check(lastPayload($m)['pending'] === false && $m->value('Ringtone') === true, json_encode(lastPayload($m)));
    check($m->timers['ConfirmTimeout']['interval'] === 0, 'timer still running');
    check(count(array_filter(TestRegistry::$log, fn ($l) => str_contains($l, 'did not confirm'))) === 1, json_encode(TestRegistry::$log));
};

$tests['Bell mute: feedback "null" as text counts as muted'] = function () {
    $m = makeBell(['TargetVariable' => 402]);
    update($m, 402, 'null');
    check($m->value('Ringtone') === false, 'not muted');
    update($m, 402, '{"ts":"x","target":"**701"}');
    check($m->value('Ringtone') === true, 'not on');
};

$tests['Bell mute: switch follows feedback on apply'] = function () {
    TestRegistry::$variables[402] = ['VariableType' => 3, 'VariableUpdated' => 0];
    TestRegistry::$values[402] = '';
    $m = makeBell(['TargetVariable' => 402]);
    check($m->value('Ringtone') === false, 'not muted');
};

$tests['Bell mute: changed call target is sent while on (with feedback)'] = function () {
    $m = makeBell(['TargetVariable' => 402]);
    check(TestRegistry::$requests === [], json_encode(TestRegistry::$requests));
    $m->properties['CallTarget'] = '**702';
    $m->ApplyChanges();
    check(TestRegistry::$requests === [[401, '{"action":"set_target","target":"**702"}']], json_encode(TestRegistry::$requests));
};

$tests['Bell mute: changed call target is not sent while muted'] = function () {
    TestRegistry::$variables[402] = ['VariableType' => 3, 'VariableUpdated' => 0];
    TestRegistry::$values[402] = '';
    $m = makeBell(['TargetVariable' => 402]);
    $m->properties['CallTarget'] = '**702';
    $m->ApplyChanges();
    check(TestRegistry::$requests === [], json_encode(TestRegistry::$requests));
};

$tests['Bell mute: call target without feedback sent once, then on change'] = function () {
    $m = makeBell();
    check(TestRegistry::$requests === [[401, ON_CMD]], 'first valid configuration: ' . json_encode(TestRegistry::$requests));
    $m->ApplyChanges();
    check(count(TestRegistry::$requests) === 1, 'sent again');
    $m->properties['CallTarget'] = '**702';
    $m->ApplyChanges();
    check(TestRegistry::$requests[1] === [401, '{"action":"set_target","target":"**702"}'], json_encode(TestRegistry::$requests));
};

$tests['Bell mute: failed command → error, switch unchanged'] = function () {
    $m = makeBell(['TargetVariable' => 402]);
    TestRegistry::$requestFails = true;
    $m->RequestAction('SetMuted', 'true');
    check(lastError($m) !== null && lastPayload($m)['pending'] === false && $m->value('Ringtone') === true, json_encode($m->visu));
    check($m->timers['ConfirmTimeout']['interval'] === 0, 'timer started');
    TestRegistry::reset();
    $m = makeBell();
    TestRegistry::$requestFails = true;
    $m->RequestAction('SetMuted', 'true');
    check($m->value('Ringtone') === true && lastError($m) !== null, 'switched without feedback');
};

$tests['Bell mute: invalid call target → 201'] = function () {
    foreach (['sip:**613', '**70 1', str_repeat('1', 21)] as $target) {
        TestRegistry::reset();
        check(makeBell(['CallTarget' => $target])->currentStatus() === 201, $target);
    }
    TestRegistry::reset();
    check(makeBell(['CallTarget' => '#613*'])->currentStatus() === 102, 'valid target');
};

$tests['Bell mute: no command while misconfigured'] = function () {
    $m = makeBell(['MailFlapVariable' => 404]);
    $m->RequestAction('SetMuted', 'true');
    $m->RequestAction('Ringtone', false);
    check(TestRegistry::$requests === [] && lastError($m) !== null, json_encode(TestRegistry::$requests));
};

$tests['Bell mute: invalid value from the tile → error, no command'] = function () {
    $m = makeBell();
    TestRegistry::$requests = [];
    $m->RequestAction('SetMuted', 'kaputt');
    check(lastError($m) !== null && TestRegistry::$requests === [], json_encode($m->visu));
};
