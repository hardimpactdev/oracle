<?php

return [

    'driver' => env('ORACLE_DRIVER', 'gemini'),

    'model' => env('ORACLE_MODEL', 'gemini-2.5-flash'),

    'timeout' => (int) env('ORACLE_TIMEOUT', 180),

    'recall_url' => env('ORACLE_RECALL_URL', 'https://recall.beast'),

    'extra_path' => env('ORACLE_EXTRA_PATH', implode(':', array_filter([
        (getenv('HOME') ?: '/home/oracle').'/.bun/bin',
        (getenv('HOME') ?: '/home/oracle').'/.local/bin',
    ]))),

];
