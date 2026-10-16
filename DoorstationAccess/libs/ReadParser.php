<?php

declare(strict_types=1);

// Parses a doorstation/rfid/read message {"ts","type","id"}.
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
    public static function parse(string $raw, int $now): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'invalid_json'];
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
