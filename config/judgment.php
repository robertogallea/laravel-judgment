<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Engine connection
    |--------------------------------------------------------------------------
    |
    | The connection in "engines" that answers a Judgment's Questions, unless
    | the Judgment chooses another from its engine() method.
    |
    */

    'engine' => env('JUDGMENT_ENGINE', 'jev'),

    /*
    |--------------------------------------------------------------------------
    | Engine connections
    |--------------------------------------------------------------------------
    |
    | Each connection names a driver: "jev", a driver registered through
    | EngineManager::extend(), or a class implementing
    | RobertoGallea\Judgment\Contracts\Engine, resolved from the container.
    |
    | Pin Jev's model to an exact version: thresholds are calibrated against
    | it (ADR-0008). An alias such as "jev-latest" is refused in production,
    | and warned about elsewhere, unless allow_aliases is on. Rate-limited and
    | overloaded requests are retried with exponential backoff, honouring
    | Jev's retry-after hints; the timeout is in seconds, per attempt.
    |
    */

    'engines' => [

        'jev' => [
            'driver' => 'jev',
            'key' => env('TYPESAFE_API_KEY'),
            'url' => env('TYPESAFE_BASE_URL', 'https://api.typesafe.ai'),
            'model' => env('JUDGMENT_JEV_MODEL', 'jev-1.13.0'),
            'allow_aliases' => env('JUDGMENT_JEV_ALLOW_ALIASES', false),
            'timeout' => env('JUDGMENT_JEV_TIMEOUT', 10),
            'retries' => env('JUDGMENT_JEV_RETRIES', 3),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failure mode
    |--------------------------------------------------------------------------
    |
    | What happens when the Engine fails to produce an Assessment. "throw"
    | raises an EngineFailed exception; "unassessed" returns the Judgment in
    | an Unassessed state instead, which the application must handle. A failure is never
    | turned into a default Outcome.
    |
    */

    'failure' => env('JUDGMENT_FAILURE', 'throw'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Whether to log each assessed, unassessed and decided Judgment, with the
    | Judgment, model, engine request id and Outcome as context, and to which
    | channel. A null channel uses the application's default channel.
    |
    */

    'log' => env('JUDGMENT_LOG', true),

    'log_channel' => env('JUDGMENT_LOG_CHANNEL'),

];
