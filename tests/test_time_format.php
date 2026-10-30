<?php

// Shared time format of both modules ("Today 18:08" or "15.10. 18:08")

$tests['TimeFormat: same day'] = function () {
    check(TimeFormat::short(1792166880, 1792166880 + 3600, 'Today') === 'Today 18:08', TimeFormat::short(1792166880, 1792166880 + 3600, 'Today'));
};

$tests['TimeFormat: other day'] = function () {
    check(TimeFormat::short(1792166880 - 86400, 1792166880, 'Today') === '15.10. 18:08', TimeFormat::short(1792166880 - 86400, 1792166880, 'Today'));
};

$tests['TimeFormat: just before midnight is yesterday'] = function () {
    $time = mktime(23, 59, 0, 10, 15, 2026);
    $now = mktime(0, 1, 0, 10, 16, 2026);
    check(TimeFormat::short($time, $now, 'Today') === '15.10. 23:59', TimeFormat::short($time, $now, 'Today'));
};
