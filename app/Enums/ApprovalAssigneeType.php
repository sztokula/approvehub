<?php

namespace App\Enums;

enum ApprovalAssigneeType: string
{
    case Role = 'role';
    case User = 'user';
}
