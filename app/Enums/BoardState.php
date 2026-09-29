<?php

namespace App\Enums;

enum BoardState: string
{
    case Locked = 'locked';
    case Unlocked = 'unlocked';
    case ShuttingDown = 'shutting_down';
}
