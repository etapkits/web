<?php

namespace App\Enums;

enum CommandType: string
{
    case Unlock = 'unlock';
    case Lock = 'lock';
    case Shutdown = 'shutdown';
}
