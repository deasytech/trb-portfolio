<?php

return [

    /*
    | Per-request limits for transfer submissions, in minor units (cents).
    */
    'transfers' => [
        'min_minor' => (int) env('TRANSFER_MIN_MINOR', 100),
        'max_minor' => (int) env('TRANSFER_MAX_MINOR', 10_000_000),
        'submissions_per_minute' => (int) env('TRANSFER_SUBMISSIONS_PER_MINUTE', 5),
    ],

];
