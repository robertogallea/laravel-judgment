<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;

/** A Judgment that also holds a model statically, which is shared state and never its Subject. */
final class TemplatedReturn extends Judgment
{
    public static ?ReturnRequest $template = null;

    public function __construct(public readonly ReturnRequest $request) {}

    public function evidence(): array
    {
        return ['reason' => $this->request->reason, 'template' => self::$template?->reason];
    }

    public function questions(): array
    {
        return ['matches' => Likelihood::that('Does the reason match the template?')];
    }
}
