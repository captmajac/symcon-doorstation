<?php

// Mailbox state machine (rebuild of the user's mail detection script)

const T = 1000;

$tests['MailboxLogic: door opens → empty, timer stops'] = function () {
    $r = MailboxLogic::onChange('door', false, true, T, 0, MailboxLogic::MAIL);
    check($r === ['state' => 0, 'timer' => 'stop', 'doorChanged' => T], json_encode($r));
};

$tests['MailboxLogic: flap open → newspaper, then closed → mail'] = function () {
    $r = MailboxLogic::onChange('flap', true, false, T, 0, 0);
    check($r === ['state' => 2, 'timer' => 'start', 'doorChanged' => 0], json_encode($r));
    $r = MailboxLogic::onChange('flap', false, false, T + 3, 0, 2);
    check($r === ['state' => 1, 'timer' => 'start', 'doorChanged' => 0], json_encode($r));
};

$tests['MailboxLogic: flap within 5 s after door is ignored, at 5 s it counts'] = function () {
    $r = MailboxLogic::onChange('flap', true, false, T, T - 4, 0);
    check($r === ['state' => 0, 'timer' => 'keep', 'doorChanged' => T - 4], json_encode($r));
    $r = MailboxLogic::onChange('flap', true, false, T, T - 5, 0);
    check($r['state'] === 2 && $r['timer'] === 'start', json_encode($r));
};

$tests['MailboxLogic: door closes → unchanged, change time noted'] = function () {
    $r = MailboxLogic::onChange('door', false, false, T, 0, 0);
    check($r === ['state' => 0, 'timer' => 'keep', 'doorChanged' => T], json_encode($r));
};

$tests['MailboxLogic: flap changes while door open → empty'] = function () {
    $r = MailboxLogic::onChange('flap', true, true, T, 0, 1);
    check($r === ['state' => 0, 'timer' => 'stop', 'doorChanged' => 0], json_encode($r));
};
