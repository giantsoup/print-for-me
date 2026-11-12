<?php

namespace App\Enums;

enum PrintQueuePlateStatus: string
{
    case Queued = 'queued';
    case Printing = 'printing';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Queued => $target === self::Printing,
            self::Printing => in_array($target, [self::Queued, self::Done, self::Failed], true),
            self::Done, self::Failed => $target === self::Queued,
        };
    }
}
