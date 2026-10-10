<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compiled Blade Views
    |--------------------------------------------------------------------------
    |
    | A serverless host only allows writes under /tmp, so the compiled view
    | path moves there as soon as the VERCEL marker is present. Locally the
    | framework default inside storage/ is kept.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        env('VERCEL') ? '/tmp/framework/views' : realpath(__DIR__.'/../storage/framework/views')
    ),

];
