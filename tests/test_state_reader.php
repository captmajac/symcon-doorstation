<?php

// Tolerant reading of state and call target values (text, boolean, integer, JSON)

$tests['StateReader: text, case and spaces'] = function () {
    check(StateReader::isOn('open', 'open', 'closed') === true, 'open');
    check(StateReader::isOn(' Closed ', 'open', 'closed') === false, 'Closed');
};

$tests['StateReader: JSON with state'] = function () {
    check(StateReader::isOn('{"ts":"x","state":"motion"}', 'motion', 'idle') === true, 'motion');
    check(StateReader::isOn('{"ts":"x","state":"idle"}', 'motion', 'idle') === false, 'idle');
};

$tests['StateReader: boolean and integer'] = function () {
    check(StateReader::isOn(true, 'open', 'closed') === true, 'true');
    check(StateReader::isOn(false, 'open', 'closed') === false, 'false');
    check(StateReader::isOn(1, 'open', 'closed') === true, '1');
    check(StateReader::isOn(0, 'open', 'closed') === false, '0');
};

$tests['StateReader: unknown values are null'] = function () {
    foreach (['xyz', '{"state":"xyz"}', '', 2, '{"ts":"x"}'] as $value) {
        check(StateReader::isOn($value, 'open', 'closed') === null, json_encode($value));
    }
};

$tests['StateReader: call target muted'] = function () {
    foreach (['', 'null', 'NULL', ' ', '{"ts":"x","target":null}', '{"target":""}'] as $value) {
        check(StateReader::target($value) === null, json_encode($value));
    }
};

$tests['StateReader: call target number'] = function () {
    check(StateReader::target('{"target":"**701"}') === '**701', 'JSON');
    check(StateReader::target(' **701 ') === '**701', 'text');
};
