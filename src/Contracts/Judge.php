<?php

namespace RobertoGallea\Judgment\Contracts;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Unassessed;

interface Judge
{
    /**
     * Ask the Judgment's Questions over its Evidence in one Engine round.
     *
     * @throws EngineFailed when the Engine fails, unless judgment.failure is "unassessed"
     */
    public function assess(Judgment $judgment): Assessment|Unassessed;
}
