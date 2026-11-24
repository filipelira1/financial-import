<?php

return [
    // Rows per queued chunk job. Each chunk is validated and written in one transaction.
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 1000),

    // Upload limit in kilobytes (nginx and php.ini allow 20 MB).
    'max_upload_kb' => (int) env('IMPORT_MAX_UPLOAD_KB', 20480),

    'disk' => env('IMPORT_DISK', 'local'),

    'queue' => env('IMPORT_QUEUE', 'imports'),
];
