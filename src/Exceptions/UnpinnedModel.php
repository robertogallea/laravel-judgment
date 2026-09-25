<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;

/** A model alias may change beneath calibrated thresholds, so production refuses it unless allowed (ADR-0008). */
final class UnpinnedModel extends LogicException
{
    public static function for(string $model): self
    {
        return new self(sprintf('The Engine model "%s" is an alias, not an exact version, so it may change beneath calibrated thresholds. Pin an exact version such as "jev-1.13.0", or set allow_aliases on the connection.', $model));
    }
}
