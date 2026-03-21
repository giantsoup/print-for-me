<?php

namespace App\Enums;

final class PrintRequestStatus
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const PRINTING = 'printing';

    public const COMPLETE = 'complete';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::ACCEPTED,
            self::PRINTING,
            self::COMPLETE,
        ];
    }
}
