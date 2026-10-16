<?php

declare(strict_types=1);

require_once __DIR__ . '/libs/ReadParser.php';
require_once __DIR__ . '/libs/ScheduleEvaluator.php';
require_once __DIR__ . '/libs/DecisionLog.php';
require_once __DIR__ . '/libs/RecentList.php';

class DoorstationAccess extends IPSModule
{
    const STATUS_NO_READ = 201;
    const STATUS_NO_SCRIPT = 202;
    const STATUS_READ_NOT_STRING = 203;
    const ARCHIVE_MODULE = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    const NAME_MAX = 40;
    // order below the instance: access list, category with the schedules, reader status
    const POS_RECENT = 10;
    const POS_KEYS = 20;
    const POS_READER = 30;
    const READER_PROFILE = 'DSA.ReaderStatus';
    const READER_VALUES = ['offline' => 0, 'degraded' => 1, 'online' => 2];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('ReadVariable', 0);
        $this->RegisterPropertyInteger('StatusVariable', 0);
        $this->RegisterPropertyInteger('DoorScript', 0);

        // last decisions [{ts, id, result}], newest first
        $this->RegisterAttributeString('Decisions', '[]');
        // last processed read "ts|id"
        $this->RegisterAttributeString('LastRead', '');

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        foreach ($this->GetMessageList() as $sender => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($sender, $message);
            }
        }
        foreach ($this->GetReferenceList() as $id) {
            $this->UnregisterReference($id);
        }

        $read = $this->ReadPropertyInteger('ReadVariable');
        $statusVar = $this->ReadPropertyInteger('StatusVariable');
        $script = $this->ReadPropertyInteger('DoorScript');
        foreach ([$read, $statusVar, $script] as $id) {
            if ($id > 0) {
                $this->RegisterReference($id);
            }
        }

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, KR_READY);
        }

        $this->RegisterVariableString('Recent', $this->Translate("Accesses"), '~HTMLBox', self::POS_RECENT);
        $keys = $this->keysCategory();
        // schedules of earlier versions were directly below the instance
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $child) {
            if ($this->isKeyEvent($child)) {
                IPS_SetParent($child, $keys);
            }
        }
        if ($statusVar > 0) {
            $this->registerReaderProfile();
        }
        $this->MaintainVariable('Reader', $this->Translate("Reader"), 1, self::READER_PROFILE, self::POS_READER, $statusVar > 0);

        $status = $this->configStatus();
        $this->SetStatus($status);
        if ($status !== 102) {
            return;
        }
        $this->RegisterMessage($read, VM_UPDATE);
        $this->RegisterMessage($read, VM_DELETE);
        if ($statusVar > 0) {
            $this->RegisterMessage($statusVar, VM_UPDATE);
            $this->RegisterMessage($statusVar, VM_DELETE);
        }
        if ($script > 0) {
            $this->RegisterMessage($script, OM_UNREGISTER);
        }
        $this->RegisterMessage($keys, OM_CHILDADDED);
        $this->RegisterMessage($keys, OM_CHILDREMOVED);
        foreach ($this->keyEvents() as $event) {
            $this->watchKey($event);
        }
        // the archive may not be ready during startup; KR_READY runs ApplyChanges again
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->refresh();
        }
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (!$this->archiveOk()) {
            array_unshift($form['elements'], ['type' => 'Label', 'caption' => $this->archiveHint()]);
        }
        return json_encode($form);
    }

    public function GetVisualizationTile()
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP;
        $initial = json_encode(json_encode($this->payload(), $flags), $flags);
        return file_get_contents(__DIR__ . '/module.html') . "\n<script>handleMessage($initial);</script>";
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case KR_READY:
                $this->ApplyChanges();
                break;
            case VM_UPDATE:
                if ($this->GetStatus() !== 102) {
                    break;
                }
                if ($SenderID === $this->ReadPropertyInteger('ReadVariable')) {
                    $this->handleRead((string) $Data[0]);
                } elseif ($SenderID === $this->ReadPropertyInteger('StatusVariable')) {
                    $this->refresh();
                }
                break;
            case OM_CHILDADDED:
                if ($this->isKeyEvent((int) $Data[0])) {
                    $this->watchKey((int) $Data[0]);
                }
                $this->refresh();
                break;
            case OM_CHILDREMOVED:
            case OM_CHANGENAME:
            case EM_CHANGEACTIVE:
                $this->refresh();
                break;
            case VM_DELETE:
            case OM_UNREGISTER:
                $this->SetStatus($this->configStatus());
                break;
        }
    }

    // Key management from the tile; value is JSON {"id", "name"|"active"}
    public function RequestAction($Ident, $Value)
    {
        $error = $this->manageKey((string) $Ident, json_decode((string) $Value, true));
        if ($error !== null) {
            $this->UpdateVisualizationValue(json_encode(['error' => $error]));
            return;
        }
        $this->refresh();
    }

    // null on success, otherwise the translated error
    private function manageKey(string $action, $data): ?string
    {
        $id = is_array($data) && is_string($data['id'] ?? null) ? ReadParser::normalizeId($data['id']) : null;
        if ($id === null) {
            return $this->Translate("Invalid key");
        }
        $name = null;
        if (in_array($action, ['CreateKey', 'RenameKey'], true)) {
            $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
            if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
                return $this->Translate("Name must be 1 to 40 characters");
            }
        }
        if ($action === 'SetKeyActive' && !is_bool($data['active'] ?? null)) {
            return $this->Translate("Invalid key");
        }
        $event = $this->keyEvent($id);

        switch ($action) {
            case 'CreateKey':
                if ($event > 0) {
                    return $this->Translate("Key already exists");
                }
                if (!in_array($id, array_column($this->recent(), 'id'), true)) {
                    return $this->Translate("Key not in recent reads");
                }
                $this->createKeyEvent($id, $name);
                return null;
            case 'RenameKey':
            case 'SetKeyActive':
            case 'DeleteKey':
                if ($event === 0) {
                    return $this->Translate("Unknown key");
                }
                if ($action === 'RenameKey') {
                    IPS_SetName($event, $name);
                } elseif ($action === 'DeleteKey') {
                    IPS_DeleteEvent($event);
                } else {
                    IPS_SetEventActive($event, $data['active']);
                }
                return null;
        }
        return $this->Translate("Invalid key");
    }

    // Weekly schedule: action 1 access, 2 locked; one group per weekday (Mon = 1 … Sun = 64), access from 00:00
    private function createKeyEvent(string $id, string $name): void
    {
        $event = IPS_CreateEvent(2);
        IPS_SetName($event, $name);
        IPS_SetEventScheduleAction($event, 1, $this->Translate("Access"), 0x2E7D32, '');
        IPS_SetEventScheduleAction($event, 2, $this->Translate("Locked"), 0xC62828, '');
        for ($day = 0; $day < 7; $day++) {
            IPS_SetEventScheduleGroup($event, $day, 1 << $day);
            IPS_SetEventScheduleGroupPoint($event, $day, 0, 0, 0, 0, 1);
        }
        IPS_SetEventActive($event, true);
        // attach only when complete: the tile visualization seems to keep a schedule that was empty when it appeared
        IPS_SetParent($event, $this->keysCategory());
        IPS_SetIdent($event, 'key_' . $id);
        // OM_CHILDADDED may arrive before the ident is set
        $this->watchKey($event);
    }

    private function archiveID(): int
    {
        $ids = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE);
        return $ids[0] ?? 0;
    }

    private function archiveOk(): bool
    {
        $archive = $this->archiveID();
        $read = $this->ReadPropertyInteger('ReadVariable');
        return $archive > 0 && $read > 0 && AC_GetLoggingStatus($archive, $read);
    }

    // Last accesses from the archive of the read variable; [] without archiving
    private function recent(): array
    {
        if (!$this->archiveOk()) {
            return [];
        }
        $read = $this->ReadPropertyInteger('ReadVariable');
        $rows = AC_GetLoggedValues($this->archiveID(), $read, 0, 0, 20);
        $current = ['TimeStamp' => IPS_GetVariable($read)['VariableUpdated'], 'Value' => (string) GetValue($read)];
        $nameOf = function (string $id): ?string {
            $event = $this->keyEvent($id);
            return $event > 0 ? IPS_GetObject($event)['ObjectName'] : null;
        };
        return RecentList::build($rows, $current, $nameOf, new DecisionLog($this->ReadAttributeString('Decisions')));
    }

    private function handleRead(string $raw): void
    {
        $read = ReadParser::parse($raw, $this->now(), IPS_GetVariable($this->ReadPropertyInteger('ReadVariable'))['VariableUpdated']);
        if (!$read['ok']) {
            $this->LogMessage(sprintf($this->Translate("Read ignored: %s, value: %s"), $read['error'], $raw), KL_WARNING);
            return;
        }
        $id = $read['id'];

        $semaphore = 'DSA_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($semaphore, 2000)) {
            $this->LogMessage(sprintf($this->Translate("Read dropped: %s, instance busy"), $id), KL_WARNING);
            return;
        }
        try {
            $key = $read['ts'] . '|' . $id;
            if ($this->ReadAttributeString('LastRead') === $key) {
                return;
            }
            $this->WriteAttributeString('LastRead', $key);

            $event = $this->keyEvent($id);
            $name = $event > 0 ? IPS_GetObject($event)['ObjectName'] : $id;
            if ($read['stale']) {
                $result = 'stale';
                $this->LogMessage(sprintf($this->Translate("Read discarded: %s is too old"), $id), KL_WARNING);
            } elseif ($event === 0) {
                $result = 'unknown';
                $this->LogMessage(sprintf($this->Translate("Unknown key %s"), $id), KL_MESSAGE);
            } else {
                $now = (new DateTimeImmutable())->setTimestamp($this->now());
                $result = ScheduleEvaluator::evaluate(IPS_GetEvent($event), $now);
                if ($result === 'inactive') {
                    $this->LogMessage(sprintf($this->Translate("Key %s is inactive"), $name), KL_MESSAGE);
                } elseif ($result === 'outside_schedule') {
                    $this->LogMessage(sprintf($this->Translate("Key %s is outside its schedule"), $name), KL_MESSAGE);
                }
            }

            $log = new DecisionLog($this->ReadAttributeString('Decisions'));
            $log->add($read['ts'], $id, $result);
            $this->WriteAttributeString('Decisions', $log->toJson());
        } finally {
            IPS_SemaphoreLeave($semaphore);
        }

        if ($result === 'allowed' && $this->ReadPropertyInteger('DoorScript') === 0) {
            $this->LogMessage(sprintf($this->Translate("Access granted for %s, no door opener script"), $name), KL_MESSAGE);
        } elseif ($result === 'allowed') {
            IPS_RunScriptEx($this->ReadPropertyInteger('DoorScript'), [
                'KEY_ID'   => $id,
                'KEY_NAME' => $name,
                'KEY_TYPE' => $read['type'],
                'TS'       => $read['ts'],
            ]);
            $this->LogMessage(sprintf($this->Translate("Door opener started for %s"), $name), KL_MESSAGE);
        }
        $this->refresh();
    }

    // Weekly schedule event of a key, 0 if unknown
    private function keyEvent(string $id): int
    {
        $event = @IPS_GetObjectIDByIdent('key_' . $id, $this->keysCategory());
        return $event !== false && IPS_EventExists($event) ? $event : 0;
    }

    private function isKeyEvent(int $id): bool
    {
        return IPS_EventExists($id) && str_starts_with(IPS_GetObject($id)['ObjectIdent'], 'key_');
    }

    // Weekly schedule events of all keys
    private function keyEvents(): array
    {
        return array_values(array_filter(IPS_GetChildrenIDs($this->keysCategory()), fn ($id) => $this->isKeyEvent($id)));
    }

    // Category "Keys" below the instance holding the schedules, created if missing
    private function keysCategory(): int
    {
        $id = @IPS_GetObjectIDByIdent('Keys', $this->InstanceID);
        if ($id !== false && IPS_CategoryExists($id)) {
            return $id;
        }
        $id = IPS_CreateCategory();
        IPS_SetName($id, $this->Translate("Keys"));
        IPS_SetPosition($id, self::POS_KEYS);
        IPS_SetParent($id, $this->InstanceID);
        IPS_SetIdent($id, 'Keys');
        return $id;
    }

    private function watchKey(int $event): void
    {
        $this->RegisterMessage($event, OM_CHANGENAME);
        $this->RegisterMessage($event, EM_CHANGEACTIVE);
    }

    private function archiveHint(): string
    {
        return $this->Translate("The read variable is not archived. Enable archiving to see recent accesses.");
    }

    private function readerStatus(): ?string
    {
        $statusVar = $this->ReadPropertyInteger('StatusVariable');
        return $statusVar > 0 && IPS_VariableExists($statusVar) ? trim((string) GetValue($statusVar)) : null;
    }

    private function registerReaderProfile(): void
    {
        if (IPS_VariableProfileExists(self::READER_PROFILE)) {
            return;
        }
        IPS_CreateVariableProfile(self::READER_PROFILE, 1);
        IPS_SetVariableProfileIcon(self::READER_PROFILE, 'Network');
        IPS_SetVariableProfileAssociation(self::READER_PROFILE, 0, $this->Translate("Offline"), '', 0xE53935);
        IPS_SetVariableProfileAssociation(self::READER_PROFILE, 1, $this->Translate("Degraded"), '', 0xFDD835);
        IPS_SetVariableProfileAssociation(self::READER_PROFILE, 2, $this->Translate("Online"), '', 0x43A047);
    }

    // Data for the tile, see spec 7.1
    private function payload(): array
    {
        $now = (new DateTimeImmutable())->setTimestamp($this->now());
        $keys = [];
        foreach ($this->keyEvents() as $event) {
            $object = IPS_GetObject($event);
            $data = IPS_GetEvent($event);
            $keys[] = [
                'id'          => substr($object['ObjectIdent'], 4),
                'name'        => $object['ObjectName'],
                'event'       => $event,
                'active'      => (bool) $data['EventActive'],
                'allowed_now' => ScheduleEvaluator::evaluate($data, $now) === 'allowed',
            ];
        }
        return [
            'status'     => $this->readerStatus(),
            'archive_ok' => $this->archiveOk(),
            'recent'     => $this->recent(),
            'keys'       => $keys,
        ];
    }

    // HTML box for the old WebFront
    private function renderHtml(array $payload): string
    {
        if (!$payload['archive_ok']) {
            return '<div>' . htmlspecialchars($this->archiveHint()) . '</div>';
        }
        $hints = [
            'unknown'          => $this->Translate("unknown"),
            'inactive'         => $this->Translate("inactive"),
            'outside_schedule' => $this->Translate("outside schedule"),
            'stale'            => $this->Translate("discarded (too old)"),
        ];
        $html = '<table>';
        foreach ($payload['recent'] as $entry) {
            $name = $entry['name'] ?? sprintf($this->Translate("Unknown (%s)"), $entry['id']);
            // color on each cell, the WebFront styles td itself
            $td = match (true) {
                $entry['result'] === null      => '<td>',
                $entry['result'] === 'allowed' => '<td style="color:#2E7D32">',
                default                        => '<td style="color:#C62828">',
            };
            $cells = $td . date('d.m.Y H:i', $entry['time']) . '</td>' . $td . htmlspecialchars($name) . '</td>';
            if ($entry['result'] === null || $entry['result'] === 'allowed') {
                $html .= "<tr>$cells</tr>";
            } else {
                $title = htmlspecialchars($hints[$entry['result']] ?? $entry['result']);
                $html .= "<tr title=\"$title\">$cells</tr>";
            }
        }
        return $html . '</table>';
    }

    // Updates HTML box and tile
    private function refresh(): void
    {
        $payload = $this->payload();
        $this->SetValue('Recent', $this->renderHtml($payload));
        if ($payload['status'] !== null) {
            // unknown texts count as offline
            $this->SetValue('Reader', self::READER_VALUES[$payload['status']] ?? 0);
        }
        $this->UpdateVisualizationValue(json_encode($payload));
    }

    protected function now(): int
    {
        return time();
    }

    private function configStatus(): int
    {
        $read = $this->ReadPropertyInteger('ReadVariable');
        if ($read <= 0 || !IPS_VariableExists($read)) {
            return self::STATUS_NO_READ;
        }
        // the script is optional, but a selected one must exist
        $script = $this->ReadPropertyInteger('DoorScript');
        if ($script > 0 && !IPS_ScriptExists($script)) {
            return self::STATUS_NO_SCRIPT;
        }
        if (IPS_GetVariable($read)['VariableType'] !== 3) {
            return self::STATUS_READ_NOT_STRING;
        }
        return 102;
    }
}
