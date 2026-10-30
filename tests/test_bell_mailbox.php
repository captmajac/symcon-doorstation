<?php

// Bell module: mailbox from mail flap and mailbox door, delayed notification, reset by hand

function mailBell(array $props = []): TestBell
{
    return makeBell(array_merge(['MailFlapVariable' => 404, 'MailDoorVariable' => 405, 'NotifyScript' => 502], $props));
}

$tests['Bell mailbox: flap open → newspaper, closed → mail, timer restarts'] = function () {
    $m = mailBell();
    update($m, 404, 'open');
    check($m->value('Mailbox') === 2 && $m->timers['Notify']['interval'] === 60000, json_encode([$m->value('Mailbox'), $m->timers['Notify']]));
    $m->timers['Notify']['interval'] = 1;
    update($m, 404, 'closed');
    check($m->value('Mailbox') === 1 && $m->timers['Notify']['interval'] === 60000, 'not mail');
    check(lastPayload($m)['mailbox'] === 1, json_encode(lastPayload($m)));
};

$tests['Bell mailbox: notification only when not empty'] = function () {
    $m = mailBell();
    update($m, 404, 'open');
    update($m, 404, 'closed');
    $m->RequestAction('Notify', '');
    check(TestRegistry::$scriptRuns === [[502, ['STATE' => 1, 'TEXT' => 'Mail', 'TS' => TestRegistry::$now]]], json_encode(TestRegistry::$scriptRuns));
    check($m->timers['Notify']['interval'] === 0, 'timer still running');
    TestRegistry::reset();
    $m = mailBell();
    $m->RequestAction('Notify', '');
    check(TestRegistry::$scriptRuns === [], json_encode(TestRegistry::$scriptRuns));
};

$tests['Bell mailbox: door open → empty; flap 4 s later ignored'] = function () {
    $m = mailBell();
    update($m, 404, 'open');
    update($m, 405, 'open');
    check($m->value('Mailbox') === 0 && $m->timers['Notify']['interval'] === 0, 'not empty');
    TestRegistry::$now += 2;
    update($m, 405, 'closed');
    TestRegistry::$now += 4;
    update($m, 404, 'closed');
    check($m->value('Mailbox') === 0, 'flap counted');
};

$tests['Bell mailbox: same state again → no change, no timer'] = function () {
    $m = mailBell();
    update($m, 404, 'closed');
    check($m->value('Mailbox') === 0 && $m->timers['Notify']['interval'] === 0, 'changed');
};

$tests['Bell mailbox: any operation resets to empty'] = function () {
    $m = mailBell();
    update($m, 404, 'open');
    $m->RequestAction('Mailbox', 2);
    check($m->value('Mailbox') === 0 && $m->timers['Notify']['interval'] === 0, 'not reset');
    update($m, 404, 'closed');
    $m->RequestAction('ResetMailbox', '');
    check($m->value('Mailbox') === 0 && $m->timers['Notify']['interval'] === 0, 'not reset from tile');
};

$tests['Bell mailbox: configurable delay'] = function () {
    $m = mailBell(['NotifyDelay' => 120]);
    update($m, 404, 'open');
    check($m->timers['Notify']['interval'] === 120000, json_encode($m->timers['Notify']));
};

$tests['Bell mailbox: boolean state variables'] = function () {
    TestRegistry::$variables[404] = ['VariableType' => 0, 'VariableUpdated' => 0];
    TestRegistry::$values[404] = false;
    $m = mailBell();
    update($m, 404, true);
    check($m->value('Mailbox') === 2, (string) $m->value('Mailbox'));
};

$tests['Bell mailbox: timer stops when the mailbox is unconfigured'] = function () {
    $m = mailBell();
    update($m, 404, 'open');
    $m->properties['MailFlapVariable'] = 0;
    $m->properties['MailDoorVariable'] = 0;
    $m->ApplyChanges();
    check($m->timers['Notify']['interval'] === 0, 'timer still running');
    $m->RequestAction('Notify', '');
    check(TestRegistry::$scriptRuns === [], 'notified');
};

$tests['Bell mailbox: reset without mailbox → error'] = function () {
    $m = makeBell();
    $m->RequestAction('ResetMailbox', '');
    check(lastError($m) !== null, json_encode($m->visu));
};

$tests['Bell mailbox: unknown action → error'] = function () {
    $m = makeBell();
    $m->RequestAction('Whatever', '');
    check(lastError($m) !== null, json_encode($m->visu));
};

$tests['Bell mailbox: timers stop on error status'] = function () {
    $m = mailBell(['TargetVariable' => 402]);
    update($m, 404, 'open');
    $m->RequestAction('SetMuted', 'true');
    $m->properties['RingVariable'] = 0;
    $m->ApplyChanges();
    check($m->timers['Notify']['interval'] === 0 && $m->timers['ConfirmTimeout']['interval'] === 0, json_encode($m->timers));
};
