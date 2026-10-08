<?php

/*
 * Settings for the v1 compatibility layer (the API that existing clients of the legacy system call).
 */
return [
    // Same static key the legacy API checked in the X-Api-Key header, so clients don't change anything.
    'api_key' => env('LEGACY_API_KEY'),

    // The legacy system stored and returned local Bangkok time without an offset. We store UTC and
    // convert back to this zone when answering v1 requests.
    'timezone' => env('LEGACY_TIMEZONE', 'Asia/Bangkok'),
];
