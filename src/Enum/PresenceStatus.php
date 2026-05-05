<?php

declare(strict_types=1);

namespace App\Enum;

enum PresenceStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Away = 'away';
}
