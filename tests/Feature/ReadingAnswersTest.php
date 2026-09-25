<?php

use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Exceptions\UndeclaredQuestion;
use RobertoGallea\Judgment\Exceptions\WrongQuestionKind;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ProductListing;

function assessListing(): RobertoGallea\Judgment\Assessment
{
    app()->instance(Engine::class, new FakeEngine(['counterfeit' => .10, 'tone' => .50]));

    return (new ProductListing('Genuine leather wallet'))->assess();
}

it('names the declared Questions when asked for an undeclared key', function () {
    assessListing()->likelihood('counterfit');
})->throws(UndeclaredQuestion::class, ProductListing::class.' declares no Question "counterfit". Declared: counterfeit, tone.');

it('names the actual kind when a Question is read as the wrong kind', function () {
    assessListing()->likelihood('tone');
})->throws(WrongQuestionKind::class, 'Question "tone" on '.ProductListing::class.' is an Opinion, not a Likelihood.');
