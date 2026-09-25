<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Engine
    |--------------------------------------------------------------------------
    |
    | The class implementing RobertoGallea\Judgment\Contracts\Engine that
    | answers every Judgment's Questions. It is resolved from the container.
    |
    */

    'engine' => env('JUDGMENT_ENGINE'),

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
