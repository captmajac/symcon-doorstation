<?php

// Usage: ../tools/php/php tests/run.php
// Tests the module against the Symcon stub, without a Symcon server.

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/stubs.php';
foreach (glob(__DIR__ . '/../DoorstationAccess/libs/*.php') as $lib) {
    require_once $lib;
}
require_once __DIR__ . '/../DoorstationAccess/module.php';
require_once __DIR__ . '/helpers.php';

$tests = [];
foreach (glob(__DIR__ . '/test_*.php') as $file) {
    require $file;
}

class CheckFailed extends Exception {}
function check(bool $ok, string $msg) { if (!$ok) throw new CheckFailed($msg); }

$failed = 0;
foreach ($tests as $name => $test) {
    TestRegistry::reset();
    try {
        $test();
        echo "  ok    $name\n";
    } catch (Throwable $e) {
        $failed++;
        $where = $e instanceof CheckFailed ? '' : ' [' . get_class($e) . ' in ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
        echo "  FAIL  $name: {$e->getMessage()}$where\n";
    }
}
echo "\n" . (count($tests) - $failed) . '/' . count($tests) . " passed\n";
exit($failed ? 1 : 0);
