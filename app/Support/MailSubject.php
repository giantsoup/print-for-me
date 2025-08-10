<?php

namespace App\Support;

final class MailSubject
{
    public static function make(string $suffix): string
    {
        $prefix = (string) config('prints.subject_prefix', "[Taylor’s Print Services]");
        return rtrim($prefix) . ' ' . ltrim($suffix);
    }
}
