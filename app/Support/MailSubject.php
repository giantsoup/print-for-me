<?php

namespace App\Support;

final class MailSubject
{
    public static function make(string $suffix): string
    {
        $prefix = (string) config('prints.subject_prefix', '[Print for Me]');

        return rtrim($prefix).' '.ltrim($suffix);
    }
}
