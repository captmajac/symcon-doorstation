<?php

// Usage: ../tools/php/php tests/run.php
// Tests the module against the Symcon stub, without a Symcon server.

date_default_timezone_set('Europe/Berlin');
// Symcon shows warnings and notices of a module as errors (e.g. instance cannot be created); unless suppressed with @
set_error_handler(function (int $errno, string $errstr, string $file, int $line) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $file, $line);
});
require_once __DIR__ . '/stubs.php';
foreach (glob(__DIR__ . '/../libs/*.php') as $lib) {
    require_once $lib;
}
// modules of the library; a module folder that does not exist yet is skipped
foreach (['DoorstationAccess', 'DoorstationBell'] as $module) {
    foreach (glob(__DIR__ . "/../$module/libs/*.php") as $lib) {
        require_once $lib;
    }
    if (is_file(__DIR__ . "/../$module/module.php")) {
        require_once __DIR__ . "/../$module/module.php";
    }
}
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
