<?php

namespace App\Enums\Administrator\Workflow;

enum ApprovalAction: string
{
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }
}
