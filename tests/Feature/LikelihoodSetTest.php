<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Exceptions\MalformedEngineResponse;
use RobertoGallea\Judgment\Exceptions\UndeclaredLabel;
use RobertoGallea\Judgment\Exceptions\WrongQuestionKind;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Flag;
use RobertoGallea\Judgment\Tests\Fixtures\PostModeration;
use RobertoGallea\Judgment\Tests\Fixtures\ProductListing;

/**
 * Assess a PostModeration, overriding the scripted answers given.
 *
 * @param  array<string, float>  $answers
 */
function assessPost(array $answers = [], ?FakeEngine &$engine = null): Assessment
{
    $engine = new FakeEngine([
        'flags.hate' => .10,
        'flags.spam' => .85,
        'flags.self_harm' => .45,
        'topics.politics' => .60,
        'topics.sport' => .05,
        ...$answers,
    ]);
    app()->instance(Engine::class, $engine);

    return (new PostModeration('Buy followers now!'))->assess();
}

it('asks the Engine one independent Likelihood per label of an array, under dotted keys', function () {
    assessPost(engine: $engine);

    $questions = $engine->requests[0]->questions;

    expect($questions['topics.politics'])->toBeInstanceOf(Likelihood::class)
        ->and($questions['topics.politics']->instructions)->toBe('Is the post about politics?')
        ->and($questions['topics.sport']->instructions)->toBe('Is the post about sport?');
});

it('asks the Engine each enum case\'s own complete question', function () {
    assessPost(engine: $engine);

    $questions = $engine->requests[0]->questions;

    expect(array_map(fn (Likelihood $likelihood) => $likelihood->instructions, $questions))->toBe([
        'flags.hate' => 'Does the post attack people based on a protected characteristic?',
        'flags.spam' => 'Is the post unsolicited promotion?',
        'flags.self_harm' => 'Does the post express intent or encouragement of self-harm?',
        'topics.politics' => 'Is the post about politics?',
        'topics.sport' => 'Is the post about sport?',
    ]);
});

it('reads the Likelihood of each label of the set as one answer', function () {
    $flags = assessPost()->likelihoods('flags');

    expect($flags->of('spam')->probability())->toBe(.85)
        ->and($flags->of(Flag::Hate)->probability())->toBe(.10)
        ->and($flags->of(Flag::SelfHarm)->above(.40))->toBeTrue();
});

it('names the Likelihood Set when it is read as a single Likelihood', function () {
    assessPost()->likelihood('flags');
})->throws(WrongQuestionKind::class, 'Question "flags" on '.PostModeration::class.' is a Likelihood Set, not a Likelihood.');

it('names the actual kind when a Classification is read as a Likelihood Set', function () {
    app()->instance(Engine::class, new FakeEngine(['counterfeit' => .10, 'tone' => ['neutral' => .70, 'hyped' => .30]]));

    (new ProductListing('Genuine leather wallet'))->assess()->likelihoods('tone');
})->throws(WrongQuestionKind::class, 'Question "tone" on '.ProductListing::class.' is a Classification, not a Likelihood Set.');

it('reads the highest Likelihood in the set', function () {
    $flags = assessPost()->likelihoods('flags');

    expect($flags->max()->probability())->toBe(.85)
        ->and($flags->max()->above(.80))->toBeTrue();
});

it('lists the labels at or above a threshold, highest first, as enum cases when enum-backed', function () {
    $flags = assessPost()->likelihoods('flags');

    expect($flags->above(.40))->toBe([Flag::Spam, Flag::SelfHarm])
        ->and($flags->above(.10))->toBe([Flag::Spam, Flag::SelfHarm, Flag::Hate])
        ->and($flags->above(.90))->toBe([]);
});

it('lists the labels above a threshold as strings when declared from an array', function () {
    expect(assessPost(['topics.sport' => .70])->likelihoods('topics')->above(.50))->toBe(['sport', 'politics']);
});

it('names the declared labels when asked about an undeclared one', function () {
    assessPost()->likelihoods('flags')->of('violence');
})->throws(UndeclaredLabel::class, 'The Likelihood Set declares no label "violence". Declared: hate, spam, self_harm.');

it('rejects an Engine response that leaves a label of the set unanswered, naming its dotted key', function () {
    app()->instance(Engine::class, new FakeEngine([
        'flags.hate' => .10, 'flags.spam' => .85, 'topics.politics' => .60, 'topics.sport' => .05,
    ]));

    (new PostModeration('Buy followers now!'))->assess();
})->throws(MalformedEngineResponse::class, 'The Engine did not answer Question "flags.self_harm" on '.PostModeration::class.'.');
