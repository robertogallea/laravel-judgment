<?php

use RobertoGallea\Judgment\Exceptions\UndeclaredLabel;
use RobertoGallea\Judgment\Tests\Fixtures\Department;

it('reads the most probable label of a Classification', function () {
    $language = assessTicket(['language' => ['english' => .15, 'italian' => .80, 'other' => .05]])->classification('language');

    expect($language->label())->toBe('italian');
});

it('tells whether a label is the most probable one', function () {
    $language = assessTicket(['language' => ['english' => .15, 'italian' => .80, 'other' => .05]])->classification('language');

    expect($language->is('italian'))->toBeTrue()
        ->and($language->is('english'))->toBeFalse();
});

it('exposes the probability of each label', function () {
    $language = assessTicket(['language' => ['english' => .15, 'italian' => .80, 'other' => .05]])->classification('language');

    expect($language->probabilityOf('english'))->toBe(.15)
        ->and($language->probabilities())->toBe(['english' => .15, 'italian' => .80, 'other' => .05]);
});

it('is as confident as the margin between the top label and the runner-up', function () {
    expect(assessTicket(['language' => ['english' => .15, 'italian' => .80, 'other' => .05]])->classification('language')->confidence())
        ->toEqualWithDelta(.65, 1e-9)
        ->and(assessTicket(['language' => ['english' => .45, 'italian' => .45, 'other' => .10]])->classification('language')->confidence())
        ->toEqualWithDelta(0.0, 1e-9);
});

it('reads the most probable label as an enum case when the labels come from a backed enum', function () {
    $department = assessTicket(['department' => ['billing' => .70, 'technical' => .20, 'other' => .10]])->classification('department');

    expect($department->label())->toBe(Department::Billing)
        ->and($department->is(Department::Billing))->toBeTrue()
        ->and($department->is(Department::Technical))->toBeFalse()
        ->and($department->probabilityOf(Department::Technical))->toBe(.20);
});

it('names the declared labels when asked about an undeclared one', function () {
    assessTicket([])->classification('language')->probabilityOf('spanish');
})->throws(UndeclaredLabel::class, 'The Classification declares no label "spanish". Declared: english, italian, other.');

it('refuses to compare against an undeclared label', function () {
    assessTicket([])->classification('language')->is('spanish');
})->throws(UndeclaredLabel::class);
