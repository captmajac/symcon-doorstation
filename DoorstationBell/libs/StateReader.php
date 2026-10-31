<?php

declare(strict_types=1);

// Reads the values of MQTT client variables tolerantly: a MQTT client may store a single field
// (text, boolean, integer) or the full JSON of the doorstation.
class StateReader
{
    // true/false for $on/$off, null for anything else
    public static function isOn($value, string $on, string $off): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }
        if (!is_string($value)) {
            return null;
        }
        $data = json_decode($value, true);
        if (is_array($data)) {
            return array_key_exists('state', $data) && !is_array($data['state']) ? self::isOn($data['state'], $on, $off) : null;
        }
        $text = strtolower(trim($value));
        return $text === strtolower($on) ? true : ($text === strtolower($off) ? false : null);
    }

    // Muted or not from the feedback of doorstation/sip/target: the boolean "muted"
    // (survives MQTT clients that split the JSON and drop null), JSON with "muted", or the call target
    public static function muted($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $data = json_decode($value, true);
            if (is_array($data) && is_bool($data['muted'] ?? null)) {
                return $data['muted'];
            }
        }
        return self::target($value) === null;
    }

    // Call target, null = muted
    public static function target($value): ?string
    {
        if (is_string($value)) {
            $data = json_decode($value, true);
            if (is_array($data)) {
                $value = $data['target'] ?? null;
            }
        }
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);
        return $text === '' || strtolower($text) === 'null' ? null : $text;
    }
}
