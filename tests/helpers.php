<?php

// Shared test helpers

// Module with a test clock
class TestAccess extends DoorstationAccess
{
    protected function now(): int { return TestRegistry::$now; }
}

// Instance 2 with read variable 100 (string), status variable 101 (string) and script 300
function makeAccess(array $props = []): TestAccess
{
    TestRegistry::$variables[100] ??= ['VariableType' => 3, 'VariableUpdated' => 0];
    TestRegistry::$values[100] ??= '';
    TestRegistry::$variables[101] ??= ['VariableType' => 3, 'VariableUpdated' => 0];
    TestRegistry::$values[101] ??= 'online';
    TestRegistry::$scripts[] = 300;
    $m = new TestAccess(2);
    $m->Create();
    $m->properties = array_merge($m->properties, ['ReadVariable' => 100, 'StatusVariable' => 0, 'DoorScript' => 300], $props);
    $m->ApplyChanges();
    return $m;
}

// Key event below instance 2 (or $parent); default: access all week
function addKey(string $id, string $name, bool $active = true, ?array $groups = null, int $parent = 2): int
{
    $e = IPS_CreateEvent(2);
    IPS_SetParent($e, $parent);
    IPS_SetIdent($e, "key_$id");
    IPS_SetName($e, $name);
    IPS_SetEventActive($e, $active);
    TestRegistry::$events[$e]['ScheduleGroups'] = $groups
        ?? [['ID' => 0, 'Days' => 127, 'Points' => [['ID' => 0, 'Start' => ['Hour' => 0, 'Minute' => 0, 'Second' => 0], 'ActionID' => 1]]]];
    return $e;
}

// Read arrives in variable 100, $age seconds old
function read(DoorstationAccess $m, string $id, int $age = 0, string $type = 'card'): void
{
    TestRegistry::$values[100] = json_encode(['ts' => date('Y-m-d\TH:i:s.vP', TestRegistry::$now - $age), 'type' => $type, 'id' => $id]);
    TestRegistry::$variables[100]['VariableUpdated'] = TestRegistry::$now;
    $m->MessageSink(TestRegistry::$now, 100, VM_UPDATE, [TestRegistry::$values[100], true, '']);
}

// Reads in the given order newest first (oldest is read first), one second apart
function readsOf(DoorstationAccess $m, array $ids): void
{
    foreach (array_reverse($ids) as $id) {
        TestRegistry::$now++;
        read($m, $id);
    }
}

function lastError(IPSModule $m): ?string
{
    $last = json_decode((string) end($m->visu), true);
    return $last['error'] ?? null;
}

// Read one second after the previous one
function readLogged(DoorstationAccess $m, string $id, string $type = 'card'): void
{
    TestRegistry::$now++;
    read($m, $id, 0, $type);
}

function lastPayload(IPSModule $m): array
{
    foreach (array_reverse($m->visu) as $v) {
        $data = json_decode($v, true);
        if (!isset($data['error'])) {
            return $data;
        }
    }
    return [];
}

// Bell module with a test clock
class TestBell extends DoorstationBell
{
    protected function now(): int { return TestRegistry::$now; }
}

// Instance 3: bell 400, command 401, call target 402, motion 403, mail flap 404, mailbox door 405 (all strings);
// scripts 500 (ring), 501 (motion), 502 (notification)
function makeBell(array $props = []): TestBell
{
    foreach ([400 => '', 401 => '', 402 => '**701', 403 => 'idle', 404 => 'closed', 405 => 'closed'] as $id => $value) {
        TestRegistry::$variables[$id] ??= ['VariableType' => 3, 'VariableUpdated' => 0];
        TestRegistry::$values[$id] ??= $value;
    }
    array_push(TestRegistry::$scripts, 500, 501, 502);
    $m = new TestBell(3);
    $m->Create();
    $m->properties = array_merge($m->properties, ['RingVariable' => 400, 'CommandVariable' => 401, 'CallTarget' => '**701'], $props);
    $m->ApplyChanges();
    return $m;
}

// Variable $var of the bell module gets $value (VM_UPDATE as Symcon sends it: value, changed, old value)
function update(DoorstationBell $m, int $var, $value): void
{
    $old = TestRegistry::$values[$var] ?? '';
    TestRegistry::$values[$var] = $value;
    TestRegistry::$variables[$var]['VariableUpdated'] = TestRegistry::$now;
    $m->MessageSink(TestRegistry::$now, $var, VM_UPDATE, [$value, $value !== $old, $old]);
}

// Options of an enumeration presentation as [value => caption]
function enumCaptions($presentation): array
{
    $out = [];
    foreach (json_decode($presentation['OPTIONS'], true) as $o) {
        $out[json_encode($o['Value'])] = $o['Caption'];
    }
    return $out;
}
