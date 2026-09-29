<?php

namespace App\Enums;

enum DisplayState: string
{
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Offline = 'offline';
    case Locked = 'locked';
    case Unlocked = 'unlocked';
    case ShuttingDown = 'shutting_down';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Onay bekliyor',
            self::Rejected => 'Reddedildi',
            self::Offline => 'Çevrimdışı',
            self::Locked => 'Kilitli',
            self::Unlocked => 'Açık',
            self::ShuttingDown => 'Kapanıyor',
        };
    }
}
