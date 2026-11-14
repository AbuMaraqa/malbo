<?php

return [
    'name' => 'Reports',

    // The day boundaries of every report: orders are stored in the app timezone, reports count days
    // in this one (MALBO: Asia/Jerusalem). Empty: the app timezone.
    'timezone' => env('REPORTS_TIMEZONE'),
];
