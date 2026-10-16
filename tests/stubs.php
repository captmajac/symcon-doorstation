<?php

// Minimal IP-Symcon replacement so the module can be tested without a Symcon server.
// Only covers what the module actually uses.

class TestRegistry
{
    public static array $values = [];     // VariableID => value
    public static array $variables = [];  // VariableID => ['VariableType' => int, 'VariableUpdated' => int]
    public static array $scripts = [];    // script IDs
    public static array $log = [];        // LogMessage
    public static int $nextID = 10000;
    public static int $runlevel = KR_READY;
    public static int $now = 1792166880;  // test clock (2026-10-16 18:08:00 +02:00, a Friday)
    public static array $objects = [];    // ID => ['parent','ident','name','type'] (events)
    public static array $events = [];     // ID => IPS_GetEvent array
    public static array $scriptRuns = []; // [scriptID, params]
    public static array $archive = [];    // VariableID => [['TimeStamp','Value']], newest first
    public static array $logging = [];    // VariableID => bool
    public static ?int $archiveID = null;
    public static bool $semaphoreFree = true;

    public static function reset(): void
    {
        self::$values = [];
        self::$variables = [];
        self::$scripts = [];
        self::$log = [];
        self::$nextID = 10000;
        self::$runlevel = KR_READY;
        self::$now = 1792166880;
        self::$objects = [];
        self::$events = [];
        self::$scriptRuns = [];
        self::$archive = [];
        self::$logging = [];
        self::$archiveID = null;
        self::$semaphoreFree = true;
    }
}

// Values are arbitrary here; real Symcon provides them.
const KR_READY = 10103;
const OM_UNREGISTER = 10402;
const OM_CHANGENAME = 10404;
const OM_CHILDADDED = 10412;
const OM_CHILDREMOVED = 10413;
const VM_DELETE = 10602;
const VM_UPDATE = 10603;
const EM_CHANGEACTIVE = 10804;
const KL_MESSAGE = 10201;
const KL_WARNING = 10204;

class IPSModule
{
    public int $InstanceID;
    public array $properties = [];
    public array $attributes = [];
    public array $variables = [];   // Ident => VariableID
    public array $messages = [];    // [SenderID, Message]
    public array $references = [];
    public array $status = [];
    public array $visu = [];        // UpdateVisualizationValue payloads
    public int $visualizationType = 0;

    public function __construct($InstanceID)
    {
        $this->InstanceID = $InstanceID;
    }

    public function Create() {}
    public function ApplyChanges() {}
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {}
    public function RequestAction($Ident, $Value) {}

    protected function RegisterPropertyInteger($Name, $Default) { $this->properties[$Name] = $Default; }
    protected function ReadPropertyInteger($Name) { return (int) $this->properties[$Name]; }
    protected function RegisterAttributeString($Name, $Default) { $this->attributes[$Name] ??= $Default; }
    protected function ReadAttributeString($Name) { return (string) $this->attributes[$Name]; }
    protected function WriteAttributeString($Name, $Value) { $this->attributes[$Name] = $Value; }

    protected function RegisterMessage($SenderID, $Message) { $this->messages[] = [$SenderID, $Message]; }
    protected function UnregisterMessage($SenderID, $Message)
    {
        $this->messages = array_values(array_filter($this->messages, fn ($m) => $m !== [$SenderID, $Message]));
    }
    protected function GetMessageList()
    {
        $list = [];
        foreach ($this->messages as [$sender, $message]) {
            $list[$sender][] = $message;
        }
        return $list;
    }
    protected function RegisterReference($ID) { $this->references[] = $ID; }
    protected function UnregisterReference($ID) { $this->references = array_values(array_diff($this->references, [$ID])); }
    protected function GetReferenceList() { return $this->references; }

    protected function RegisterVariableString($Ident, $Name, $Profile = '', $Position = 0)
    {
        if (!isset($this->variables[$Ident])) {
            $id = TestRegistry::$nextID++;
            $this->variables[$Ident] = $id;
            TestRegistry::$values[$id] = '';
        }
        return $this->variables[$Ident];
    }
    protected function GetIDForIdent($Ident) { return $this->variables[$Ident] ?? false; }
    protected function SetValue($Ident, $Value) { TestRegistry::$values[$this->variables[$Ident]] = $Value; return true; }

    protected function SetStatus($Status) { $this->status[] = $Status; }
    protected function GetStatus() { return $this->currentStatus(); }
    public function currentStatus() { return end($this->status) ?: 102; }
    protected function LogMessage($Message, $Type) { TestRegistry::$log[] = $Message; }
    protected function Translate($Text) { return $Text; }
    protected function SendDebug($Message, $Data, $Format) {}
    protected function SetVisualizationType($Type) { $this->visualizationType = $Type; }
    protected function UpdateVisualizationValue($Value) { $this->visu[] = $Value; }

    // test helper
    public function value($Ident) { return TestRegistry::$values[$this->variables[$Ident]]; }
}

function IPS_GetKernelRunlevel() { return TestRegistry::$runlevel; }
function IPS_VariableExists($ID) { return isset(TestRegistry::$variables[$ID]); }
function IPS_GetVariable($ID)
{
    if (!isset(TestRegistry::$variables[$ID])) {
        throw new Exception("Variable #$ID does not exist");
    }
    return TestRegistry::$variables[$ID];
}
function IPS_ScriptExists($ID) { return in_array($ID, TestRegistry::$scripts, true); }
function GetValue($ID) { return TestRegistry::$values[$ID]; }

function IPS_RunScriptEx($ID, $Parameters) { TestRegistry::$scriptRuns[] = [$ID, $Parameters]; return true; }
function IPS_SemaphoreEnter($Name, $Milliseconds) { return TestRegistry::$semaphoreFree; }
function IPS_SemaphoreLeave($Name) { return true; }

// Objects (only events are created in tests)
function IPS_GetObjectIDByIdent($Ident, $ParentID)
{
    foreach (TestRegistry::$objects as $id => $o) {
        if ($o['parent'] === $ParentID && $o['ident'] === $Ident) {
            return $id;
        }
    }
    return false;  // Symcon also raises a warning here
}
function IPS_GetChildrenIDs($ID)
{
    return array_keys(array_filter(TestRegistry::$objects, fn ($o) => $o['parent'] === $ID));
}
function IPS_GetObject($ID)
{
    $o = TestRegistry::$objects[$ID];
    return ['ObjectID' => $ID, 'ParentID' => $o['parent'], 'ObjectIdent' => $o['ident'], 'ObjectName' => $o['name'], 'ObjectType' => $o['type']];
}
function IPS_SetParent($ID, $ParentID) { TestRegistry::$objects[$ID]['parent'] = $ParentID; return true; }
function IPS_SetIdent($ID, $Ident) { TestRegistry::$objects[$ID]['ident'] = $Ident; return true; }
function IPS_SetName($ID, $Name) { TestRegistry::$objects[$ID]['name'] = $Name; return true; }

// Events
function IPS_CreateEvent($Type)
{
    $id = TestRegistry::$nextID++;
    TestRegistry::$objects[$id] = ['parent' => 0, 'ident' => '', 'name' => 'Unnamed Object', 'type' => 4];
    TestRegistry::$events[$id] = ['EventID' => $id, 'EventType' => $Type, 'EventActive' => false, 'ScheduleActions' => [], 'ScheduleGroups' => []];
    return $id;
}
function IPS_EventExists($ID) { return isset(TestRegistry::$events[$ID]); }
function IPS_GetEvent($ID)
{
    if (!isset(TestRegistry::$events[$ID])) {
        throw new Exception("Event #$ID does not exist");
    }
    return TestRegistry::$events[$ID];
}
function IPS_SetEventActive($ID, $Active) { TestRegistry::$events[$ID]['EventActive'] = $Active; return true; }
function IPS_SetEventScheduleAction($ID, $ActionID, $Name, $Color, $Script)
{
    TestRegistry::$events[$ID]['ScheduleActions'][] = ['ID' => $ActionID, 'Name' => $Name, 'Color' => $Color];
    return true;
}
function IPS_SetEventScheduleGroup($ID, $GroupID, $Days)
{
    TestRegistry::$events[$ID]['ScheduleGroups'][$GroupID] = ['ID' => $GroupID, 'Days' => $Days, 'Points' => []];
    return true;
}
function IPS_SetEventScheduleGroupPoint($ID, $GroupID, $PointID, $Hour, $Minute, $Second, $ActionID)
{
    TestRegistry::$events[$ID]['ScheduleGroups'][$GroupID]['Points'][$PointID] =
        ['ID' => $PointID, 'Start' => ['Hour' => $Hour, 'Minute' => $Minute, 'Second' => $Second], 'ActionID' => $ActionID];
    return true;
}
function IPS_DeleteEvent($ID)
{
    unset(TestRegistry::$events[$ID], TestRegistry::$objects[$ID]);
    return true;
}

// Archive
function IPS_GetInstanceListByModuleID($ModuleID)
{
    return $ModuleID === '{43192F0B-135B-4CE7-A0A7-1475603F3060}' && TestRegistry::$archiveID !== null ? [TestRegistry::$archiveID] : [];
}
function AC_GetLoggingStatus($InstanceID, $VariableID) { return TestRegistry::$logging[$VariableID] ?? false; }
function AC_GetLoggedValues($InstanceID, $VariableID, $StartTime, $EndTime, $Limit)
{
    $rows = TestRegistry::$archive[$VariableID] ?? [];
    return $Limit > 0 ? array_slice($rows, 0, $Limit) : $rows;
}
