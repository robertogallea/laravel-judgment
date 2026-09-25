<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Judgment;

final class UnscriptedJudgment extends LogicException
{
    /** @param  list<class-string<Judgment>>  $scripted */
    public static function for(Judgment $judgment, array $scripted): self
    {
        return new self(sprintf(
            '%s has no script in Judge::fake(). Scripted: %s.',
            $judgment::class,
            $scripted === [] ? 'none' : implode(', ', $scripted),
        ));
    }
}
