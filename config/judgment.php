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
    | raises an EngineFailed exception; "unassessed" returns an Unassessed
    | result instead, which the application must handle. A failure is never
    | turned into a default Outcome.
    |
    */

    'failure' => env('JUDGMENT_FAILURE', 'throw'),

    /*
    |--------------------------------------------------------------------------
    | Log channel
    |--------------------------------------------------------------------------
    |
    | The channel that receives an entry for each assessed, unassessed and
    | decided Judgment, with the Judgment, model, engine request id and
    | Outcome as context. Null uses the default channel; "null" silences it.
    |
    */

    'log_channel' => env('JUDGMENT_LOG_CHANNEL'),

];
