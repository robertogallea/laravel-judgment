<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Exceptions\UndeclaredQuestion;
use RobertoGallea\Judgment\Exceptions\WrongQuestionKind;
use RobertoGallea\Judgment\Tests\Fixtures\Department;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ProductListing;
use RobertoGallea\Judgment\Tests\Fixtures\SupportTicket;
use RobertoGallea\Judgment\Tests\Fixtures\TicketQuestion;

function assessListing(): Assessment
{
    app()->instance(Engine::class, new FakeEngine(['counterfeit' => .10, 'tone' => ['neutral' => .70, 'hyped' => .30]]));

    return (new ProductListing('Genuine leather wallet'))->assess();
}

it('names the declared Questions when asked for an undeclared key', function () {
    assessListing()->likelihood('counterfit');
})->throws(UndeclaredQuestion::class, ProductListing::class.' declares no Question "counterfit". Declared: counterfeit, tone.');

it('names the actual kind when a Question is read as the wrong kind', function () {
    assessListing()->likelihood('tone');
})->throws(WrongQuestionKind::class, 'Question "tone" on '.ProductListing::class.' is a Classification, not a Likelihood.');

it('names the actual kind when a Rating is read as a Classification', function () {
    assessTicket()->classification('severity');
})->throws(WrongQuestionKind::class, 'Question "severity" on '.SupportTicket::class.' is a Rating, not a Classification.');

it('reads answers by a backed-enum key as well as by a string key', function () {
    $assessment = assessTicket();

    expect($assessment->classification(TicketQuestion::Department)->label())->toBe(Department::Billing)
        ->and($assessment->rating(TicketQuestion::Severity)->level())->toBe(2);
});

it('names the declared Questions when asked for an undeclared backed-enum key', function () {
    assessTicket()->likelihood(TicketQuestion::Refund);
})->throws(UndeclaredQuestion::class, SupportTicket::class.' declares no Question "refund". Declared: language, department, severity.');
