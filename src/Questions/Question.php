<?php

namespace RobertoGallea\Judgment\Questions;

abstract class Question
{
    protected function __construct(public readonly string $instructions) {}
}
