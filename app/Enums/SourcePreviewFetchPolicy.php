<?php

namespace App\Enums;

enum SourcePreviewFetchPolicy: string
{
    case Allow = 'allow';
    case Block = 'block';

    public function label(): string
    {
        return match ($this) {
            self::Allow => 'Allow',
            self::Block => 'Block',
        };
    }
}
