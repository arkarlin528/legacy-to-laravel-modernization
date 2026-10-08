<?php

return [
    // Password for the seeded back-office login (admin@demo.test). Read through config, not env(),
    // so it still works when the production image caches configuration.
    'admin_password' => env('ADMIN_PASSWORD'),
];
