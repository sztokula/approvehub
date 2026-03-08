<?php

namespace App\Enums;

enum ApprovalDecisionStatus: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
