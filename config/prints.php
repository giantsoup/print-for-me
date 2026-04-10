<?php

$appName = env('APP_NAME', 'Print for Me');

return [
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),

    'subject_prefix' => env('MAIL_SUBJECT_PREFIX', "[{$appName}]"),

    'log_completion_email_debug' => (bool) env('PRINTS_LOG_COMPLETION_EMAIL_DEBUG', false),
];
