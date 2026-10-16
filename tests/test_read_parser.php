<?php

// ReadParser: JSON of doorstation/rfid/read, age check, ID normalization

function readJson(array $fields): string { return json_encode($fields); }
function tsAt(int $epoch): string { return date('Y-m-d\TH:i:s.vP', $epoch); }

$tests['Parser: valid read, ID normalized'] = function () {
    $now = TestRegistry::$now;
    $ts = tsAt($now);
    $r = ReadParser::parse(readJson(['ts' => $ts, 'type' => 'card', 'id' => 'C0FFEE42']), $now);
    check($r === ['ok' => true, 'id' => 'c0ffee42', 'type' => 'card', 'ts' => $ts, 'stale' => false], json_encode($r));
};

$tests['Parser: milliseconds and Z'] = function () {
    $now = TestRegistry::$now;
    $ts = gmdate('Y-m-d\TH:i:s', $now) . '.123Z';
    check(ReadParser::parse(readJson(['ts' => $ts, 'id' => 'c0ffee42']), $now)['stale'] === false, $ts);
};

$tests['Parser: +5 s and -5 s fresh, +6 s and -6 s stale'] = function () {
    $now = TestRegistry::$now;
    $stale = [];
    foreach ([5, -5, 6, -6] as $d) {
        $stale[] = ReadParser::parse(readJson(['ts' => tsAt($now + $d), 'id' => 'c0ffee42']), $now)['stale'];
    }
    check($stale === [false, false, true, true], json_encode($stale));
};

$tests['Parser: without time zone stale'] = function () {
    $now = TestRegistry::$now;
    $r = ReadParser::parse(readJson(['ts' => date('Y-m-d\TH:i:s', $now), 'id' => 'c0ffee42']), $now);
    check($r['ok'] === true && $r['stale'] === true, json_encode($r));
};

$tests['Parser: unreadable ts stale'] = function () {
    $r = ReadParser::parse(readJson(['ts' => 'yesterday+02:00', 'id' => 'c0ffee42']), TestRegistry::$now);
    check($r['ok'] === true && $r['stale'] === true, json_encode($r));
};

$tests['Parser: no JSON'] = function () {
    check(ReadParser::parse('04A1B2C3D4E5F6', TestRegistry::$now) === ['ok' => false, 'error' => 'invalid_json'], 'not invalid_json');
};

$tests['Parser: missing id or ts'] = function () {
    $now = TestRegistry::$now;
    check(ReadParser::parse(readJson(['ts' => tsAt($now)]), $now)['error'] === 'missing_field', 'no id');
    check(ReadParser::parse(readJson(['id' => 'c0ffee42']), $now)['error'] === 'missing_field', 'no ts');
    check(ReadParser::parse(readJson(['ts' => 5, 'id' => 'c0ffee42']), $now)['error'] === 'missing_field', 'ts not string');
};

$tests['Parser: id too short, too long, not hex'] = function () {
    $now = TestRegistry::$now;
    foreach (['1234567', str_repeat('a', 65), 'xyz12345'] as $id) {
        $r = ReadParser::parse(readJson(['ts' => tsAt($now), 'id' => $id]), $now);
        check(($r['error'] ?? '') === 'invalid_id', $id);
    }
};

$tests['Parser: type missing'] = function () {
    $now = TestRegistry::$now;
    $r = ReadParser::parse(readJson(['ts' => tsAt($now), 'id' => '04A1B2C3D4E5F6']), $now);
    check($r['ok'] === true && $r['type'] === '' && $r['id'] === '04a1b2c3d4e5f6', json_encode($r));
};

$tests['Parser: normalizeId'] = function () {
    check(ReadParser::normalizeId(' C0FFEE42 ') === null, 'whitespace accepted');
    check(ReadParser::normalizeId('C0FFEE42') === 'c0ffee42', 'upper');
    check(ReadParser::normalizeId(str_repeat('A', 64)) === str_repeat('a', 64), '64');
};
