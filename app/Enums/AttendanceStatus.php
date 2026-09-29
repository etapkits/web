<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Absent = 'absent';
    case Late = 'late';

    public function label(): string
    {
        return match ($this) {
            self::Absent => 'Gelmedi',
            self::Late => 'Geç geldi',
        };
    }
}
