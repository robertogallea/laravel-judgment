<?php

use RobertoGallea\Judgment\Exceptions\InvalidQuestion;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Questions\Rating;
use RobertoGallea\Judgment\Tests\Fixtures\Department;
use RobertoGallea\Judgment\Tests\Fixtures\Ticket;
use RobertoGallea\Judgment\Tests\Fixtures\TicketQuestion;

it('declares Classification labels from a list, without descriptions', function () {
    $question = Classification::of('In which language is the ticket written?', labels: ['english', 'italian']);

    expect($question->criteria())->toBe(['english' => null, 'italian' => null]);
});

it('declares Classification labels with their descriptions', function () {
    $question = Classification::of('Is the review about the product or the delivery?', labels: [
        'product' => 'The item itself: quality, fit, features',
        'delivery' => 'Shipping, packaging, courier',
    ]);

    expect($question->criteria())->toBe([
        'product' => 'The item itself: quality, fit, features',
        'delivery' => 'Shipping, packaging, courier',
    ]);
});

it('declares Classification labels from a backed enum, described by its description() method', function () {
    $question = Classification::of('Which team should handle this ticket?', labels: Department::class);

    expect($question->criteria())->toBe([
        'billing' => 'Payments, invoices, refunds, subscriptions',
        'technical' => 'Bugs, outages, integrations, errors',
        'other' => 'Anything else',
    ]);
});

it('accepts up to 255 Classification labels', function () {
    $labels = array_map(fn (int $i) => "label-$i", range(1, 255));

    expect(Classification::of('Which label fits?', labels: $labels)->criteria())->toHaveCount(255);
});

it('refuses more than 255 Classification labels', function () {
    Classification::of('Which label fits?', labels: array_map(fn (int $i) => "label-$i", range(1, 256)));
})->throws(InvalidQuestion::class, 'A Classification accepts at most 255 labels; 256 given.');

it('refuses a Likelihood without instructions', function () {
    Likelihood::that('');
})->throws(InvalidQuestion::class, 'A Question needs instructions: ask it as a complete question about the Evidence.');

it('refuses a Classification whose instructions are blank', function () {
    Classification::of("  \n", labels: ['english', 'italian']);
})->throws(InvalidQuestion::class, 'A Question needs instructions: ask it as a complete question about the Evidence.');

it('declares Rating levels, lowest first', function () {
    expect(Rating::of('How credible is the explanation?', levels: ['Not credible', 'Plausible', 'Highly credible'])->criteria())
        ->toBe(['Not credible', 'Plausible', 'Highly credible']);
});

it('accepts between 2 and 10 Rating levels', function (int $count) {
    $levels = array_map(fn (int $i) => "Level $i", range(1, $count));

    expect(Rating::of('How severe is it?', levels: $levels)->criteria())->toHaveCount($count);
})->with([2, 10]);

it('refuses a Rating with fewer than 2 or more than 10 levels', function (int $count) {
    Rating::of('How severe is it?', levels: array_map(fn (int $i) => "Level $i", range(1, $count)));
})->with([1, 11])->throws(InvalidQuestion::class, 'A Rating needs 2 to 10 levels;');

it('refuses fewer than 2 Classification labels', function () {
    Classification::of('Which label fits?', labels: ['only']);
})->throws(InvalidQuestion::class, 'A Classification needs at least 2 labels; 1 given.');

it('refuses duplicate Classification labels', function () {
    Classification::of('Which label fits?', labels: ['spam', 'ham', 'spam']);
})->throws(InvalidQuestion::class, 'A Classification\'s labels must be distinct; "spam" is repeated.');

it('refuses Classification labels from a class that is not a backed enum', function () {
    Classification::of('Which label fits?', labels: Ticket::class);
})->throws(InvalidQuestion::class, 'Classification labels must be a list, a label => description map or a backed enum class; '.Ticket::class.' given.');

it('refuses a Likelihood Set whose labels do not supply their questions', function () {
    Likelihood::each(['hate', 'spam']);
})->throws(InvalidQuestion::class, 'Each label of a Likelihood Set supplies its complete question: give a label => question map or a backed enum with a question() method.');

it('refuses a Likelihood Set from a backed enum without a question() method', function () {
    Likelihood::each(TicketQuestion::class);
})->throws(InvalidQuestion::class, 'Each label of a Likelihood Set supplies its complete question: give a label => question map or a backed enum with a question() method.');

it('refuses a Likelihood Set from a class that is not a backed enum', function () {
    Likelihood::each(Ticket::class);
})->throws(InvalidQuestion::class, 'Each label of a Likelihood Set supplies its complete question: give a label => question map or a backed enum with a question() method.');

it('refuses a Likelihood Set without labels', function () {
    Likelihood::each([]);
})->throws(InvalidQuestion::class, 'A Likelihood Set needs at least 1 label; 0 given.');

it('refuses a Likelihood Set label whose question is blank', function () {
    Likelihood::each(['hate' => 'Does the post attack a protected group?', 'spam' => ' ']);
})->throws(InvalidQuestion::class, 'A Question needs instructions: ask it as a complete question about the Evidence.');

it('refuses a Likelihood Set label containing a dot, which would blur its dotted Engine key', function () {
    Likelihood::each(['self.harm' => 'Does the post encourage self-harm?']);
})->throws(InvalidQuestion::class, 'A Likelihood Set label cannot contain a dot; "self.harm" given.');
