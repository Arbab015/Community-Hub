<?php

namespace App\Enums;

enum OwnershipStatus: string
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';
}
