<?php

namespace RobertoGallea\Judgment;

use RobertoGallea\Judgment\Exceptions\EngineFailed;

/**
 * A Judgment whose Engine failed to produce an Assessment. It deliberately has
 * no answers and no Outcome, so a failure can never become a default Outcome.
 */
final class Unassessed
{
    public function __construct(
        public readonly Judgment $judgment,
        public readonly EngineFailed $exception,
    ) {}
}
