<?php

return [
    'public_key_b64' => env('JWT_PUBLIC_KEY_B64'),
    'algo' => env('JWT_ALGO', 'RS256'),
];
