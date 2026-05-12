<?php

namespace App\Enum;

enum TransactionStatus: string
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
