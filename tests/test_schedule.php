<?php

// ScheduleEvaluator: current action of a weekly schedule (1 = access, 2 = locked)

function point(int $h, int $m, int $action, int $s = 0): array
{
    return ['Start' => ['Hour' => $h, 'Minute' => $m, 'Second' => $s], 'ActionID' => $action];
}
function plan(bool $active, array $groups): array
{
    return ['EventActive' => $active, 'ScheduleGroups' => $groups];
}
function at(string $time): DateTimeImmutable { return new DateTimeImmutable($time); }  // 2026-10-16 is a Friday

$tests['Plan: access from 08:30, at 10:05 allowed (bug of the old script)'] = function () {
    $p = plan(true, [['Days' => 127, 'Points' => [point(0, 0, 2), point(8, 30, 1)]]]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 10:05:00')) === 'allowed', 'not allowed');
};

$tests['Plan: locked from 18:00, exactly 18:00:00 outside'] = function () {
    $p = plan(true, [['Days' => 127, 'Points' => [point(0, 0, 1), point(18, 0, 2)]]]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 18:00:00')) === 'outside_schedule', 'not outside');
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 17:59:59')) === 'allowed', 'not allowed before');
};

$tests['Plan: group Mon-Fri applies on Friday, Sat-Sun does not'] = function () {
    $p = plan(true, [
        ['Days' => 96, 'Points' => [point(0, 0, 2)]],
        ['Days' => 31, 'Points' => [point(0, 0, 1)]],
    ]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 10:00:00')) === 'allowed', 'Friday');
    check(ScheduleEvaluator::evaluate($p, at('2026-10-17 10:00:00')) === 'outside_schedule', 'Saturday');
};

$tests['Plan: Saturday without matching group outside'] = function () {
    $p = plan(true, [['Days' => 31, 'Points' => [point(0, 0, 1)]]]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-17 10:00:00')) === 'outside_schedule', 'not outside');
};

$tests['Plan: unsorted points, latest start before now wins'] = function () {
    $p = plan(true, [['Days' => 127, 'Points' => [point(18, 0, 2), point(0, 0, 2), point(8, 30, 1)]]]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 10:05:00')) === 'allowed', 'not allowed');
};

$tests['Plan: seconds count'] = function () {
    $p = plan(true, [['Days' => 127, 'Points' => [point(0, 0, 2), point(8, 30, 1, 30)]]]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 08:30:29')) === 'outside_schedule', 'too early');
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 08:30:30')) === 'allowed', 'on time');
};

$tests['Plan: inactive with access inactive'] = function () {
    $p = plan(false, [['Days' => 127, 'Points' => [point(0, 0, 1)]]]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 10:00:00')) === 'inactive', 'not inactive');
};

$tests['Plan: no groups outside'] = function () {
    check(ScheduleEvaluator::evaluate(plan(true, []), at('2026-10-16 10:00:00')) === 'outside_schedule', 'not outside');
    check(ScheduleEvaluator::evaluate(['EventActive' => true], at('2026-10-16 10:00:00')) === 'outside_schedule', 'missing key');
};

$tests['Plan: group without points outside'] = function () {
    $p = plan(true, [['Days' => 127, 'Points' => []]]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 10:00:00')) === 'outside_schedule', 'not outside');
};

$tests['Plan: first point 06:00, now 05:00 outside'] = function () {
    $p = plan(true, [['Days' => 127, 'Points' => [point(6, 0, 1)]]]);
    check(ScheduleEvaluator::evaluate($p, at('2026-10-16 05:00:00')) === 'outside_schedule', 'not outside');
};
