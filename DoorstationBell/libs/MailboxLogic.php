<?php

declare(strict_types=1);

// Mailbox from mail flap and mailbox door. Called only on a real change of one of them.
// A newspaper keeps the flap open, mail lets it fall shut; the flap moves along with the door.
class MailboxLogic
{
    const EMPTY = 0;
    const MAIL = 1;
    const NEWSPAPER = 2;
    const LOCK_S = 5;  // flap changes this soon after a door change are the door moving it

    // $source 'flap' or 'door'; returns ['state', 'timer' => 'start'|'stop'|'keep', 'doorChanged']
    public static function onChange(string $source, bool $flapOpen, bool $doorOpen, int $now, int $doorChanged, int $state): array
    {
        if ($source === 'door') {
            $doorChanged = $now;
        }
        if ($doorOpen) {
            return ['state' => self::EMPTY, 'timer' => 'stop', 'doorChanged' => $doorChanged];
        }
        if ($now - $doorChanged < self::LOCK_S || $source === 'door') {
            return ['state' => $state, 'timer' => 'keep', 'doorChanged' => $doorChanged];
        }
        return ['state' => $flapOpen ? self::NEWSPAPER : self::MAIL, 'timer' => 'start', 'doorChanged' => $doorChanged];
    }
}
