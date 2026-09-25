<?php

namespace RobertoGallea\Judgment\Contracts;

use Illuminate\Foundation\Bus\PendingDispatch;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Unassessed;

interface Judge
{
    /**
     * Ask the Judgment's Questions over its Evidence in one Engine round.
     *
     * @throws EngineFailed when the Engine fails, unless judgment.throw_on_failure is off
     */
    public function assess(Judgment $judgment): Assessment|Unassessed;

    /**
     * Assess the Judgment on the queue, firing the same lifecycle events as assess().
     * Chain onQueue(), onConnection() or delay() on the result.
     */
    public function dispatch(Judgment $judgment): PendingDispatch;
}
