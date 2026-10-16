# Doorstation for IP-Symcon

IP-Symcon modules for the [doorstation](https://github.com/captmajac/doorstation). The modules are not connected to MQTT themselves: they refer to the MQTT client variables you already have, so they work with any topic layout.

## Doorstation Access (`DoorstationAccess`)

Decides on access for RFID cards, AirTags and Apple Home Key reported by the doorstation service `rfid`, starts your door opener script (optional) and shows the last accesses.

- Every key is a **weekly schedule** below the instance: the schedule name is the display name, action 1 is *Access*, action 2 *Locked*. An inactive schedule locks the key.
- Unknown keys appear in the list. Give them a name in the tile and they are unlocked (all week, restrict the schedule as you like).
- The last 5 accesses come from the **archive** of the read variable: green = access, red = denied (unknown, inactive, outside schedule, too old).

### Requirements

- IP-Symcon 9.0 or newer (tile `openObject` needs 8.2)
- MQTT client variables of `doorstation/rfid/read` (string) and optionally `doorstation/rfid/status`
- Optionally a script that opens the door

### Installation

*Kernel → Modules → Add* with the URL of this repository (or a local path), then *Add object → Instance → Doorstation Access*.

### Configuration

| Field | Content |
|---|---|
| Read variable | MQTT variable of `doorstation/rfid/read` (JSON `{"ts","type","id"}`) |
| Status variable | MQTT variable of `doorstation/rfid/status` (optional, shown as a dot in the tile and as variable *Reader*) |
| Door opener script | optional, runs when access is granted; without it the module only decides and logs |

Enable **archiving** of the read variable (*Variable → Edit → Logging*). Without it the module still decides on access, but shows no list.

Reads older than 5 seconds (or more than 5 seconds in the future, or without a time zone) are discarded.

### Keys

1. Hold the card, AirTag or iPhone to the reader: it appears as *Unknown* in the tile.
2. Tap **+**, enter a name, *Create*. The schedule is created with access all week.
3. Tap the clock to edit the schedule. *Manage keys* lists all keys: rename, switch active, delete.

### Door opener script

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

The variable *Accesses* is an HTML box with the last accesses. The variable *Reader* (Offline / Degraded / Online) exists only when a status variable is selected. The schedules below the instance can be edited there as usual; naming new keys works in the tile.

## Development

```bash
../tools/php/php tests/run.php
```

Tests run against a small Symcon stub (`tests/stubs.php`). Tile preview: `../tools/php/php -S 127.0.0.1:8765`, then open `http://127.0.0.1:8765/tests/tile-preview.html`.

## License

Apache-2.0
