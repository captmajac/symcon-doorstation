<?php

declare(strict_types=1);

// Access decision from a weekly schedule event (IPS_GetEvent): action 1 = access, anything else = locked.
class ScheduleEvaluator
{
    const ACTION_ACCESS = 1;

    // 'allowed' | 'inactive' | 'outside_schedule'
    public static function evaluate(array $event, DateTimeImmutable $now): string
    {
        if (!($event['EventActive'] ?? false)) {
            return 'inactive';
        }
        $dayBit = 1 << ((int) $now->format('N') - 1);  // Mon = 1 … Sun = 64
        $seconds = (int) $now->format('G') * 3600 + (int) $now->format('i') * 60 + (int) $now->format('s');

        $latest = -1;
        $action = null;
        foreach ($event['ScheduleGroups'] ?? [] as $group) {
            if ((($group['Days'] ?? 0) & $dayBit) === 0) {
                continue;
            }
            foreach ($group['Points'] ?? [] as $point) {
                $start = $point['Start']['Hour'] * 3600 + $point['Start']['Minute'] * 60 + $point['Start']['Second'];
                if ($start <= $seconds && $start > $latest) {
                    $latest = $start;
                    $action = $point['ActionID'];
                }
            }
        }
        return $action === self::ACTION_ACCESS ? 'allowed' : 'outside_schedule';
    }
}
