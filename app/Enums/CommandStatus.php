<?php

namespace App\Enums;

enum CommandStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Acknowledged = 'acknowledged';
    case Expired = 'expired';
}
