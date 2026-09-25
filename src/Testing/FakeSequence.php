<?php

namespace RobertoGallea\Judgment\Testing;

use Closure;
use RobertoGallea\Judgment\Exceptions\ExhaustedSequence;
use RobertoGallea\Judgment\Judgment;

/** Scripts for successive assessments of one Judgment, used once each, in order. */
final class FakeSequence
{
    /** @var list<array<string, mixed>|Closure> */
    private array $scripts;

    private int $used = 0;

    /** @param  array<string, mixed>|Closure  ...$scripts  answers, or a closure given the Judgment */
    public function __construct(array|Closure ...$scripts)
    {
        $this->scripts = array_values($scripts);
    }

    /** @return array<string, mixed>|Closure */
    public function next(Judgment $judgment): array|Closure
    {
        return $this->scripts[$this->used++] ?? throw ExhaustedSequence::for($judgment, $this->used);
    }
}
