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

// Key event under instance 2; default: access all week
function addKey(string $id, string $name, bool $active = true, ?array $groups = null): int
{
    $e = IPS_CreateEvent(2);
    IPS_SetParent($e, 2);
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

// Archived reads of variable 100, newest first; archiving enabled
function archiveReads(array $ids): void
{
    TestRegistry::$archiveID = 50;
    TestRegistry::$logging[100] = true;
    $t = TestRegistry::$now - 60;
    foreach ($ids as $id) {
        TestRegistry::$archive[100][] = ['TimeStamp' => $t, 'Value' => json_encode(['ts' => date('Y-m-d\TH:i:s.vP', $t), 'type' => 'card', 'id' => $id])];
        $t -= 60;
    }
}

function lastError(DoorstationAccess $m): ?string
{
    $last = json_decode((string) end($m->visu), true);
    return $last['error'] ?? null;
}

// Read that is also archived (one second after the previous one)
function readLogged(DoorstationAccess $m, string $id, string $type = 'card'): void
{
    TestRegistry::$archiveID = 50;
    TestRegistry::$logging[100] = true;
    TestRegistry::$archive[100] ??= [];
    TestRegistry::$now++;
    read($m, $id, 0, $type);
    array_unshift(TestRegistry::$archive[100], ['TimeStamp' => TestRegistry::$now, 'Value' => GetValue(100)]);
}

function lastPayload(DoorstationAccess $m): array
{
    foreach (array_reverse($m->visu) as $v) {
        $data = json_decode($v, true);
        if (!isset($data['error'])) {
            return $data;
        }
    }
    return [];
}
