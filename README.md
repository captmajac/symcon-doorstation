# Doorstation for IP-Symcon

IP-Symcon modules for the [doorstation](https://github.com/captmajac/doorstation). The modules are not connected to MQTT themselves: they refer to the MQTT client variables you already have, so they work with any topic layout.

## Doorstation Access (`DoorstationAccess`)

Decides on access for RFID cards, AirTags and Apple Home Key reported by the doorstation service `rfid`, runs your script on access (optional, e.g. to open the door) and shows the last accesses.

- Every key is a **weekly schedule** below the instance (one group per weekday): the schedule name is the display name, action 1 is *Access*, action 2 *Locked*. An inactive schedule locks the key.
- Unknown keys appear in the list. Give them a name in the tile and they are unlocked (all week, restrict the schedule as you like).
- The last 5 accesses come from the module's own record of its decisions (the last 20), no archiving needed: green = access, red = denied (unknown, inactive, outside schedule, too old).

### Requirements

- IP-Symcon 9.0 or newer (tile `openObject` needs 8.2)
- MQTT client variables of `doorstation/rfid/read` (string) and optionally `doorstation/rfid/status`
- Optionally a script that opens the door

### Installation

*Kernel → Modules → Add* with the URL of this repository (or a local path), then *Add object → Instance → Doorstation Access*.

### Configuration

| Field | Content |
|---|---|
| RFID key variable | MQTT variable of `doorstation/rfid/read`: the full JSON `{"ts","type","id"}` (recommended, reads older than 5 s are discarded) or only the ID (no age check, type from the length) |
| RFID reader status variable | MQTT variable of `doorstation/rfid/status` (optional, shown as a dot in the tile and as variable *Reader*) |
| Script to run on access | optional, runs when access is granted (e.g. opens the door); without it the module only decides and logs |
| Entries in the access list | 1 to 10 (default 4, fits the tile without scrolling), for tile and HTML box |


Reads older than 5 seconds (or more than 5 seconds in the future, or without a time zone) are discarded.

### Keys

1. Hold the card, AirTag or iPhone to the reader: it appears with its ID in the tile.
2. Tap **+**, enter a name, *Create*. The schedule is created with access all week.
3. Tap the clock to edit the schedule. The gear (*Manage keys*) lists all keys: rename, switch active, delete.

### Script to run on access

The script gets these parameters in `$_IPS`:

| Key | Content |
|---|---|
| `KEY_ID` | ID in lower case |
| `KEY_NAME` | name of the key |
| `KEY_TYPE` | `card` or `homekey` |
| `TS` | time stamp of the read |

```php
<?php
// e.g. switch off the alarm, play an announcement, open the lock
IPS_LogMessage('Door', 'Opening for ' . $_IPS['KEY_NAME']);
// RequestAction(12345 /* lock */, 1);
```

### Old WebFront

Below the instance, in this order: *Accesses* (HTML box with the last accesses), the schedules, and *Reader* (Offline / Degraded / Online, only when a status variable is selected). The schedules can be edited there as usual; naming new keys works in the tile.

## Doorstation Bell (`DoorstationBell`)

Ringtone muted/on, the last rings, motion and the mailbox of the doorstation, as a tile and for the old WebFront.

- **Ringtone** *Muted* / *On*: sends `set_target` to `doorstation/sip/cmd` (muted = the bell rings without a call, on = your call target). With the feedback variable the switch shows what the doorstation confirmed.
- **Ring history**: the last rings (default 3) from the module's own record, *muted* when the ringtone was muted.
- **Motion** and **Last motion**, optionally a script on motion (e.g. to switch on the door light).
- **Mailbox** *Empty* / *Mail* / *Newspaper*: the mail flap sets *Mail*, a flap that stays open (newspaper stuck in it) *Newspaper*, opening the mailbox door empties it. Flap changes up to 5 s after the door moved are ignored (the door moves the flap). Any operation in WebFront or tile empties it by hand. After the notification delay the notification script runs if the mailbox is not empty.

### Requirements

- IP-Symcon 9.0 or newer
- MQTT client variables of the doorstation (a single field such as `state`, or the full JSON)

### Configuration

| Field | Content |
|---|---|
| Bell variable | a variable of `doorstation/event/ring` (e.g. `source` or `ts`); every update is a ring. Not `gpio/input/bell`, it also reports the release |
| SIP command variable | `doorstation/sip/cmd` (string) |
| Call target | e.g. `**701` (digits, `*` and `#`, up to 20 characters, as the doorstation accepts), sent for *On* and once after the first valid configuration |
| Call target feedback | `target` of `doorstation/sip/target` (optional) |
| Motion variable | `state` of `doorstation/gpio/input/motion` (optional) |
| Mail flap / Mailbox door | `state` of `doorstation/gpio/input/mail_flap` and `mail_door` (optional, only together) |
| Script to run on ring / on motion | optional |
| Notification script | optional, mailbox |
| Notification delay | 10 to 600 s (default 60) |
| Entries in the ring history | 1 to 10 (default 3) |

Values are read tolerantly: `open`/`closed`, `motion`/`idle`, `true`/`false`, `1`/`0` or JSON with `state`. Only real changes count, a state delivered again does nothing.

### Script parameters

| Script | `$_IPS` |
|---|---|
| on ring | `TS` (Unix time), `MUTED` (bool) |
| on motion | `TS` |
| notification | `STATE` (1 mail, 2 newspaper), `TEXT` (*Mail* / *Newspaper*, translated), `TS` |

```php
<?php
// notification script, e.g. a push message
$text = date('d.m.Y - H:i', $_IPS['TS']) . ' - ' . $_IPS['TEXT'];
// IPS_RunScriptEx(12345 /* push */, ['event' => 'Info', 'description' => $text]);
```

### Old WebFront

Below the instance, in this order: *Ringtone*, *Rings* (HTML box), *Motion*, *Last motion*, *Mailbox*. You can link *Ringtone* and *Mailbox* to other places (e.g. a group "Front door").

## Development

```bash
../tools/php/php tests/run.php
```

Tests run against a small Symcon stub (`tests/stubs.php`). Tile preview: `../tools/php/php -S 127.0.0.1:8765`, then open `http://127.0.0.1:8765/tests/tile-preview.html` (access) or `tests/tile-preview-bell.html` (bell).

## License

Apache-2.0
