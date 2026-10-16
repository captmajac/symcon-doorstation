<?php

declare(strict_types=1);

// Parses a doorstation/rfid/read message {"ts","type","id"} or a bare ID (variable that holds only "id").
class ReadParser
{
    const MAX_AGE = 5;  // seconds, both directions

    // Lower-case hex ID with 8 to 64 characters, otherwise null
    public static function normalizeId(string $id): ?string
    {
        $id = strtolower($id);
        return preg_match('/^[0-9a-f]{8,64}$/', $id) ? $id : null;
    }

    // ['ok'=>true,'id','type','ts','stale'] or ['ok'=>false,'error'=>'invalid_json'|'missing_field'|'invalid_id']
    // $updated: update time of the variable, ts of a bare ID (it has no age check)
    public static function parse(string $raw, int $now, ?int $updated = null): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $id = self::normalizeId(trim($raw));
            if ($id === null) {
                return ['ok' => false, 'error' => 'invalid_json'];
            }
            return [
                'ok'    => true,
                'id'    => $id,
                // Home Key IDs have 8 bytes, card UIDs 4, 7 or 10
                'type'  => strlen($id) === 16 ? 'homekey' : 'card',
                'ts'    => date('Y-m-d\TH:i:s.vP', $updated ?? $now),
                'stale' => false,
            ];
        }
        if (!is_string($data['ts'] ?? null) || !is_string($data['id'] ?? null)) {
            return ['ok' => false, 'error' => 'missing_field'];
        }
        $id = self::normalizeId($data['id']);
        if ($id === null) {
            return ['ok' => false, 'error' => 'invalid_id'];
        }
        $ts = $data['ts'];
        return [
            'ok'    => true,
            'id'    => $id,
            'type'  => is_string($data['type'] ?? null) ? $data['type'] : '',
            'ts'    => $ts,
            'stale' => self::isStale($ts, $now),
        ];
    }

    private static function isStale(string $ts, int $now): bool
    {
        // without a time zone the time is ambiguous
        if (!preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $ts)) {
            return true;
        }
        try {
            $epoch = (new DateTimeImmutable($ts))->getTimestamp();
        } catch (Exception $e) {
            return true;
        }
        return abs($epoch - $now) > self::MAX_AGE;
    }
}
