<?php

namespace RobertoGallea\Judgment\Events;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Judgment;

/** The Engine answered every Question of a Judgment. */
final class AssessmentCompleted
{
    public function __construct(
        public readonly Judgment $judgment,
        public readonly Assessment $assessment,
    ) {}
}
