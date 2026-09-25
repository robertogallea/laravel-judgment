<?php

namespace RobertoGallea\Judgment\Contracts;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Judgment;

interface Judge
{
    /** Ask the Judgment's Questions over its Evidence in one Engine round. */
    public function assess(Judgment $judgment): Assessment;
}
