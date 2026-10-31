<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TimeFormat.php';
require_once __DIR__ . '/libs/StateReader.php';
require_once __DIR__ . '/libs/RingLog.php';
require_once __DIR__ . '/libs/MailboxLogic.php';

class DoorstationBell extends IPSModule
{
    const STATUS_MISSING = 201;
    const STATUS_NO_SCRIPT = 202;
    const STATUS_COMMAND_NOT_STRING = 203;
    const STATUS_MAILBOX_INCOMPLETE = 204;
    const STATUS_VARIABLE_MISSING = 205;
    // order below the instance as in the old view "Video Station"
    const POS_RINGTONE = 10;
    const POS_RINGS = 20;
    const POS_MOTION = 30;
    const POS_LAST_MOTION = 40;
    const POS_MAILBOX = 50;
    const VARIABLES = ['RingVariable', 'CommandVariable', 'TargetVariable', 'MotionVariable', 'MailFlapVariable', 'MailDoorVariable'];
    const SCRIPTS = ['RingScript', 'MotionScript', 'NotifyScript'];
    const ORANGE = 0xFB8C00;  // hint for mail and newspaper; same palette as the fixed colors of the access module
    const RING_GAP_S = 2;  // the doorstation sends rings at least this far apart (gpio min_interval_s of the bell)
    const CONFIRM_S = 10;  // wait this long for the doorstation to confirm muted/on

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        foreach (array_merge(self::VARIABLES, self::SCRIPTS) as $name) {
            $this->RegisterPropertyInteger($name, 0);
        }
        $this->RegisterPropertyString('CallTarget', '');
        $this->RegisterPropertyInteger('NotifyDelay', 60);
        $this->RegisterPropertyInteger('HistoryLength', 3);

        // last rings [{time, muted}], newest first
        $this->RegisterAttributeString('Rings', '[]');
        // muted/on sent and not yet confirmed: {"muted": bool}, otherwise ''
        $this->RegisterAttributeString('Pending', '');
        // last call target sent (without feedback variable)
        $this->RegisterAttributeString('SentTarget', '');
        // last real change of the mailbox door (flap lock)
        $this->RegisterAttributeInteger('DoorChanged', 0);

        foreach (['Notify', 'ConfirmTimeout', 'Midnight'] as $timer) {
            $this->RegisterTimer($timer, 0, 'IPS_RequestAction($_IPS[\'TARGET\'], "' . $timer . '", "");');
        }

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
        foreach (array_merge(self::VARIABLES, self::SCRIPTS) as $name) {
            if ($this->ReadPropertyInteger($name) > 0) {
                $this->RegisterReference($this->ReadPropertyInteger($name));
            }
        }

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, KR_READY);
        }

        $motion = $this->ReadPropertyInteger('MotionVariable') > 0;
        $mailbox = $this->hasMailbox();
        // GetIDForIdent warns for a missing ident, Symcon then refuses to create the instance
        $created = @IPS_GetObjectIDByIdent('Ringtone', $this->InstanceID) === false;
        $this->MaintainVariable('Ringtone', $this->Translate("Ringtone"), 0, $this->ringtonePresentation(), self::POS_RINGTONE, true);
        if ($created) {
            // the doorstation starts with its default call target (sip.yaml default_target)
            $this->SetValue('Ringtone', true);
        }
        $this->EnableAction('Ringtone');
        $this->defaultIcon('Ringtone', 'Speaker');
        $this->RegisterVariableString('Rings', $this->Translate("Rings"), '~HTMLBox', self::POS_RINGS);
        $this->defaultIcon('Rings', 'Clock');
        $this->MaintainVariable('Motion', $this->Translate("Motion"), 0, '~Motion', self::POS_MOTION, $motion);
        $this->MaintainVariable('LastMotion', $this->Translate("Last motion"), 1, '~UnixTimestamp', self::POS_LAST_MOTION, $motion);
        $this->MaintainVariable('Mailbox', $this->Translate("Mailbox"), 1, $this->mailboxPresentation(), self::POS_MAILBOX, $mailbox);
        if ($mailbox) {
            $this->EnableAction('Mailbox');
            // the old WebFront ignores the icon of a presentation
            $this->defaultIcon('Mailbox', 'Mail');
        }

        $status = $this->configStatus();
        $this->SetStatus($status);
        if (!$mailbox || $status !== 102) {
            $this->SetTimerInterval('Notify', 0);
        }
        if ($status !== 102) {
            $this->SetTimerInterval('ConfirmTimeout', 0);
            $this->WriteAttributeString('Pending', '');
            return;
        }
        foreach (self::VARIABLES as $name) {
            $id = $this->ReadPropertyInteger($name);
            if ($id > 0) {
                $this->RegisterMessage($id, VM_UPDATE);
                $this->RegisterMessage($id, VM_DELETE);
            }
        }
        foreach (self::SCRIPTS as $name) {
            if ($this->ReadPropertyInteger($name) > 0) {
                $this->RegisterMessage($this->ReadPropertyInteger($name), OM_UNREGISTER);
            }
        }
        // objects may not be complete during startup; KR_READY runs ApplyChanges again
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->applyCallTarget();
            $this->refresh();
        }
    }

    public function GetConfigurationForm()
    {
        return file_get_contents(__DIR__ . '/form.json');
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
                if ($SenderID === $this->ReadPropertyInteger('RingVariable')) {
                    // every update is a ring, the value may stay the same (e.g. source "bell")
                    $this->SendDebug('Ring', json_encode($Data[0]), 0);
                    $this->handleRing();
                } elseif ($SenderID === $this->ReadPropertyInteger('TargetVariable')) {
                    $this->handleTarget($Data[0]);
                } elseif ($SenderID === $this->ReadPropertyInteger('MotionVariable')) {
                    $this->handleMotion($Data);
                } elseif ($SenderID === $this->ReadPropertyInteger('MailFlapVariable')) {
                    $this->handleMailbox('flap', $Data);
                } elseif ($SenderID === $this->ReadPropertyInteger('MailDoorVariable')) {
                    $this->handleMailbox('door', $Data);
                }
                break;
            case VM_DELETE:
            case OM_UNREGISTER:
                $this->SetStatus($this->configStatus());
                break;
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident !== 'Midnight' && $this->GetStatus() !== 102) {
            $this->showError($this->Translate("Instance is not configured correctly"));
            return;
        }
        if (in_array($Ident, ['Mailbox', 'ResetMailbox', 'Notify'], true) && !$this->hasMailbox()) {
            $this->SetTimerInterval('Notify', 0);
            $this->showError($this->Translate("Invalid value"));
            return;
        }
        switch ($Ident) {
            case 'Midnight':
                $this->refresh();
                break;
            case 'Mailbox':
            case 'ResetMailbox':
                // every operation empties the mailbox, e.g. after a newspaper was pulled out
                $this->SetValue('Mailbox', MailboxLogic::EMPTY);
                $this->SetTimerInterval('Notify', 0);
                $this->refresh();
                break;
            case 'Notify':
                $this->SetTimerInterval('Notify', 0);
                $state = (int) $this->GetValue('Mailbox');
                if ($state !== MailboxLogic::EMPTY) {
                    $text = $state === MailboxLogic::NEWSPAPER ? $this->Translate("Newspaper") : $this->Translate("Mail");
                    $this->runScript('NotifyScript', ['STATE' => $state, 'TEXT' => $text, 'TS' => $this->now()]);
                }
                break;
            case 'Ringtone':
                $this->setMuted(!$Value);
                break;
            case 'SetMuted':
                $muted = json_decode((string) $Value);
                if (!is_bool($muted)) {
                    $this->showError($this->Translate("Invalid value"));
                    return;
                }
                $this->setMuted($muted);
                break;
            case 'ConfirmTimeout':
                $this->SetTimerInterval('ConfirmTimeout', 0);
                if ($this->ReadAttributeString('Pending') !== '') {
                    $this->WriteAttributeString('Pending', '');
                    $this->LogMessage($this->Translate("Doorstation did not confirm muted/on"), KL_WARNING);
                    $this->handleTarget(GetValue($this->ReadPropertyInteger('TargetVariable')));
                }
                break;
            default:
                $this->showError($this->Translate("Invalid value"));
        }
    }

    // Error text for the tile
    private function showError(string $text): void
    {
        $this->UpdateVisualizationValue(json_encode(['error' => $text]));
    }

    private function handleMotion(array $data): void
    {
        $motion = $this->realChange($data, 'motion', 'idle');
        if ($motion === null) {
            return;
        }
        $this->SetValue('Motion', $motion);
        if ($motion) {
            $this->SetValue('LastMotion', $this->now());
            $this->runScript('MotionScript', ['TS' => $this->now()]);
        }
        $this->refresh();
    }

    private function handleMailbox(string $source, array $data): void
    {
        if ($this->realChange($data, 'open', 'closed') === null) {
            return;
        }
        // current state of both; unknown counts as closed
        $isOpen = fn (string $name) => StateReader::isOn(GetValue($this->ReadPropertyInteger($name)), 'open', 'closed') === true;
        $result = MailboxLogic::onChange($source, $isOpen('MailFlapVariable'), $isOpen('MailDoorVariable'), $this->now(),
            $this->ReadAttributeInteger('DoorChanged'), (int) $this->GetValue('Mailbox'));
        $this->WriteAttributeInteger('DoorChanged', $result['doorChanged']);
        $this->SetValue('Mailbox', $result['state']);
        if ($result['timer'] === 'start') {
            $this->SetTimerInterval('Notify', max(10, min(600, $this->ReadPropertyInteger('NotifyDelay'))) * 1000);
        } elseif ($result['timer'] === 'stop') {
            $this->SetTimerInterval('Notify', 0);
        }
        $this->refresh();
    }

    // New state of a VM_UPDATE [value, changed, old value], null if it is no real change
    // (e.g. retained state delivered again, or new ts with the same state) or unknown
    private function realChange(array $data, string $on, string $off): ?bool
    {
        $new = StateReader::isOn($data[0], $on, $off);
        if ($new === null) {
            $this->LogMessage(sprintf($this->Translate("Unknown value ignored: %s"), json_encode($data[0])), KL_WARNING);
            return null;
        }
        // an unknown old value counts as a change
        return StateReader::isOn($data[2] ?? null, $on, $off) === $new ? null : $new;
    }

    private function setMuted(bool $muted): void
    {
        if (!$this->sendTarget($muted)) {
            $this->showError($this->Translate("Command could not be sent to the doorstation"));
            return;
        }
        if ($this->ReadPropertyInteger('TargetVariable') > 0) {
            // the switch follows the feedback of the doorstation
            $this->WriteAttributeString('Pending', json_encode(['muted' => $muted]));
            $this->SetTimerInterval('ConfirmTimeout', self::CONFIRM_S * 1000);
        } else {
            $this->SetValue('Ringtone', !$muted);
        }
        $this->refresh();
    }

    // false if the command variable did not take the command (e.g. no action)
    private function sendTarget(bool $muted): bool
    {
        $target = $muted ? null : trim($this->ReadPropertyString('CallTarget'));
        $command = json_encode(['action' => 'set_target', 'target' => $target], JSON_UNESCAPED_SLASHES);
        if (!@RequestAction($this->ReadPropertyInteger('CommandVariable'), $command)) {
            $this->LogMessage(sprintf($this->Translate("Command could not be sent: %s"), $command), KL_WARNING);
            return false;
        }
        $this->WriteAttributeString('SentTarget', (string) $target);
        return true;
    }

    // Call target reported by the doorstation: the switch shows it, a matching report confirms a pending switch
    private function handleTarget($value): void
    {
        $muted = StateReader::target($value) === null;
        $this->SetValue('Ringtone', !$muted);
        $pending = json_decode($this->ReadAttributeString('Pending'), true);
        if (is_array($pending) && $pending['muted'] === $muted) {
            $this->WriteAttributeString('Pending', '');
            $this->SetTimerInterval('ConfirmTimeout', 0);
        }
        $this->refresh();
    }

    // While on, a changed call target in the configuration is sent to the doorstation
    private function applyCallTarget(): void
    {
        $callTarget = trim($this->ReadPropertyString('CallTarget'));
        $feedback = $this->ReadPropertyInteger('TargetVariable');
        if ($feedback > 0) {
            $reported = StateReader::target(GetValue($feedback));
            $this->SetValue('Ringtone', $reported !== null);
            if ($reported !== null && $reported !== $callTarget) {
                $this->setMuted(false);
            }
            return;
        }
        if (!$this->GetValue('Ringtone')) {
            return;
        }
        // also the first time: the default call target of the doorstation may differ
        if ($this->ReadAttributeString('SentTarget') !== $callTarget) {
            $this->sendTarget(false);
        }
    }

    private function handleRing(): void
    {
        $muted = !$this->GetValue('Ringtone');
        $log = new RingLog($this->ReadAttributeString('Rings'));
        $last = $log->latest(1)[0]['time'] ?? 0;
        if ($this->now() - $last < self::RING_GAP_S) {
            // a second update of the same ring (e.g. a MQTT client updating the variable twice)
            $this->SendDebug('Ring', 'same ring, ignored', 0);
            return;
        }
        $log->add($this->now(), $muted);
        $this->WriteAttributeString('Rings', $log->toJson());
        $this->runScript('RingScript', ['TS' => $this->now(), 'MUTED' => $muted]);
        $this->refresh();
    }

    // Runs a selected script asynchronously; nothing happens without one
    private function runScript(string $property, array $parameters): void
    {
        $script = $this->ReadPropertyInteger($property);
        if ($script > 0) {
            IPS_RunScriptEx($script, $parameters);
        }
    }

    // Data for the tile, see spec 7.1
    private function payload(): array
    {
        $motion = $this->ReadPropertyInteger('MotionVariable') > 0;
        $lastMotion = $motion ? (int) $this->GetValue('LastMotion') : 0;
        return [
            'muted'       => !$this->GetValue('Ringtone'),
            'pending'     => $this->ReadAttributeString('Pending') !== '',
            'motion'      => $motion ? (bool) $this->GetValue('Motion') : null,
            'last_motion' => $lastMotion > 0 ? $lastMotion : null,
            'mailbox'    => $this->hasMailbox() ? (int) $this->GetValue('Mailbox') : null,
            'rings'      => (new RingLog($this->ReadAttributeString('Rings')))->latest(max(1, min(10, $this->ReadPropertyInteger('HistoryLength')))),
            // the tile opens these with openObject: the standard tile shows the logged values
            'ring_var'   => $this->ReadPropertyInteger('RingVariable'),
            'motion_var' => $motion ? $this->GetIDForIdent('Motion') : null,
        ];
    }

    // HTML box for the old WebFront: time left, "muted" right
    private function renderHtml(array $rings): string
    {
        $html = '<table style="width:100%">';
        foreach ($rings as $ring) {
            $time = TimeFormat::short($ring['time'], $this->now(), $this->Translate("Today"));
            $hint = $ring['muted'] ? $this->Translate("muted") : '';
            $html .= '<tr><td>' . $time . '</td><td style="text-align:right">' . $hint . '</td></tr>';
        }
        return $html . '</table>';
    }

    // Updates HTML box and tile
    private function refresh(): void
    {
        $now = (new DateTimeImmutable('@' . $this->now()))->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $this->SetTimerInterval('Midnight', ($now->modify('tomorrow')->getTimestamp() - $this->now() + 1) * 1000);
        $payload = $this->payload();
        $this->SetValue('Rings', $this->renderHtml($payload['rings']));
        $this->UpdateVisualizationValue(json_encode($payload));
    }

    private function hasMailbox(): bool
    {
        return $this->ReadPropertyInteger('MailFlapVariable') > 0 && $this->ReadPropertyInteger('MailDoorVariable') > 0;
    }

    // Object icon of a module variable, unless the user chose one
    private function defaultIcon(string $ident, string $icon): void
    {
        $id = $this->GetIDForIdent($ident);
        if ($id !== false && IPS_GetObject($id)['ObjectIcon'] === '') {
            IPS_SetIcon($id, $icon);
        }
    }

    private static function option($value, string $caption, int $color): array
    {
        return ['Value' => $value, 'Caption' => $caption, 'IconActive' => false, 'IconValue' => '', 'Color' => $color];
    }

    // Enumeration presentation (two buttons as in the old view): muted, on (green)
    private function ringtonePresentation(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'ICON'         => 'volume-high',
            'OPTIONS'      => json_encode([
                self::option(false, $this->Translate("Muted"), -1),
                self::option(true, $this->Translate("On"), 0x43A047),
            ]),
        ];
    }

    // Enumeration presentation: empty, mail, newspaper (mail and newspaper orange as a hint)
    private function mailboxPresentation(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'ICON'         => 'envelope',
            'OPTIONS'      => json_encode([
                self::option(MailboxLogic::EMPTY, $this->Translate("Empty"), -1),
                self::option(MailboxLogic::MAIL, $this->Translate("Mail"), self::ORANGE),
                self::option(MailboxLogic::NEWSPAPER, $this->Translate("Newspaper"), self::ORANGE),
            ]),
        ];
    }

    protected function now(): int
    {
        return time();
    }

    private function configStatus(): int
    {
        $exists = fn (string $name) => IPS_VariableExists($this->ReadPropertyInteger($name));
        // same rule as the doorstation for set_target
        $callTarget = preg_match('/^[0-9*#]{1,20}$/', trim($this->ReadPropertyString('CallTarget'))) === 1;
        if (!$exists('RingVariable') || !$exists('CommandVariable') || !$callTarget) {
            return self::STATUS_MISSING;
        }
        if (IPS_GetVariable($this->ReadPropertyInteger('CommandVariable'))['VariableType'] !== 3) {
            return self::STATUS_COMMAND_NOT_STRING;
        }
        if (($this->ReadPropertyInteger('MailFlapVariable') > 0) !== ($this->ReadPropertyInteger('MailDoorVariable') > 0)) {
            return self::STATUS_MAILBOX_INCOMPLETE;
        }
        foreach (['TargetVariable', 'MotionVariable', 'MailFlapVariable', 'MailDoorVariable'] as $name) {
            if ($this->ReadPropertyInteger($name) > 0 && !$exists($name)) {
                return self::STATUS_VARIABLE_MISSING;
            }
        }
        // scripts are optional, but a selected one must exist
        foreach (self::SCRIPTS as $name) {
            $script = $this->ReadPropertyInteger($name);
            if ($script > 0 && !IPS_ScriptExists($script)) {
                return self::STATUS_NO_SCRIPT;
            }
        }
        return 102;
    }
}
