<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Exceptions\ImpureDecision;
use RobertoGallea\Judgment\Exceptions\UndeclaredLabel;
use RobertoGallea\Judgment\Exceptions\UndeclaredLevel;
use RobertoGallea\Judgment\Exceptions\UndeclaredQuestion;
use RobertoGallea\Judgment\Exceptions\UnscriptedQuestion;
use RobertoGallea\Judgment\Exceptions\WrongQuestionKind;
use RobertoGallea\Judgment\Tests\Fixtures\Department;
use RobertoGallea\Judgment\Tests\Fixtures\Flag;
use RobertoGallea\Judgment\Tests\Fixtures\ImpureRefundDecision;
use RobertoGallea\Judgment\Tests\Fixtures\PostModeration;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\SupportTicket;
use RobertoGallea\Judgment\Tests\Fixtures\Ticket;
use RobertoGallea\Judgment\Tests\Fixtures\TicketQuestion;

it('scripts a Likelihood for unit-testing a Decision, without an Engine', function (float $abusive, RefundOutcome $expected) {
    $assessment = Assessment::fake(refundAbuse())->likelihood('abusive', $abusive)->make();

    expect($assessment->outcome())->toBe($expected);
})->with([
    'clearly abusive' => [.80, RefundOutcome::Reject],
    'ambiguous' => [.40, RefundOutcome::Escalate],
    'good faith' => [.05, RefundOutcome::Approve],
]);

it('refuses to script a Question the Judgment does not declare', function () {
    Assessment::fake(refundAbuse())->likelihood('abusve', .5);
})->throws(UndeclaredQuestion::class, RefundAbuse::class.' declares no Question "abusve". Declared: abusive.');

it('refuses to script a Question as the wrong kind', function () {
    Assessment::fake(new SupportTicket(new Ticket('Hi', 'Hello')))->likelihood('severity', .5);
})->throws(WrongQuestionKind::class, 'Question "severity" on '.SupportTicket::class.' is a Rating, not a Likelihood.');

function supportTicket(): SupportTicket
{
    return new SupportTicket(new Ticket('Doppio addebito', 'Mi avete addebitato due volte.'));
}

it('scripts a Classification by its winning label and Confidence', function () {
    $department = Assessment::fake(supportTicket())
        ->classification(TicketQuestion::Department, Department::Technical, confidence: .6)
        ->make()
        ->classification('department');

    expect($department->label())->toBe(Department::Technical)
        ->and($department->confidence())->toEqualWithDelta(.6, 1e-9)
        ->and(array_sum($department->probabilities()))->toEqualWithDelta(1.0, 1e-9);
});

it('scripts a Classification by the probability of each label', function () {
    $language = Assessment::fake(supportTicket())
        ->classification('language', ['english' => .15, 'italian' => .80, 'other' => .05])
        ->make()
        ->classification('language');

    expect($language->label())->toBe('italian')
        ->and($language->probabilityOf('english'))->toBe(.15);
});

it('refuses to script a Classification label the Question does not declare', function (string|array $label) {
    Assessment::fake(supportTicket())->classification('language', $label);
})->with([
    'winning label' => ['klingon'],
    'probability map' => [['english' => .5, 'klingon' => .5]],
])->throws(UndeclaredLabel::class, 'The Classification declares no label "klingon". Declared: english, italian, other.');

it('scripts a Rating by its most probable level and Confidence', function () {
    $severity = Assessment::fake(supportTicket())->rating('severity', 3, confidence: .7)->make()->rating('severity');

    expect($severity->level())->toBe(3)
        ->and($severity->confidence())->toEqualWithDelta(.7, 1e-9)
        ->and(array_sum($severity->probabilities()))->toEqualWithDelta(1.0, 1e-9);
});

it('scripts a Rating by the probability of each level', function () {
    $severity = Assessment::fake(supportTicket())->rating('severity', [0, .5, .5, 0])->make()->rating('severity');

    expect($severity->expected())->toBe(1.5);
});

it('refuses to script a Rating level the Question does not declare', function (int|array $level) {
    Assessment::fake(supportTicket())->rating('severity', $level);
})->with([
    'too high' => [4],
    'negative' => [-1],
    'too many probabilities' => [[.2, .2, .2, .2, .2]],
    'too few probabilities' => [[.5, .5]],
])->throws(UndeclaredLevel::class, 'The Rating "severity" on '.SupportTicket::class.' has 4 levels, 0 to 3.');

it('scripts a Likelihood Set by the Likelihood of each label, unlisted labels being 0', function () {
    $flags = Assessment::fake(new PostModeration('Buy followers now!'))
        ->likelihoodSet('flags', [Flag::Spam->value => .85, 'self_harm' => .45])
        ->make()
        ->likelihoodSet('flags');

    expect($flags->labelsAbove(.40))->toBe([Flag::Spam, Flag::SelfHarm])
        ->and($flags->of(Flag::Hate)->probability())->toBe(0.0);
});

it('refuses to script a Likelihood Set label the set does not declare', function () {
    Assessment::fake(new PostModeration('Buy followers now!'))->likelihoodSet('topics', ['cooking' => .9]);
})->throws(UndeclaredLabel::class, 'The Likelihood Set declares no label "cooking". Declared: politics, sport.');

it('fails clearly when a Decision reads a Question the fake did not script', function () {
    Assessment::fake(refundAbuse())->make()->outcome();
})->throws(UnscriptedQuestion::class, 'Question "abusive" on '.RefundAbuse::class.' was not scripted in this fake Assessment, but was read.');

it('runs each Decision twice and fails when the Outcomes differ', function () {
    Assessment::fake(refundAbuse())->likelihood('abusive', .5)->make()->decide(new ImpureRefundDecision);
})->throws(ImpureDecision::class, ImpureRefundDecision::class.' returned Approve, then Reject, for the same Assessment. A Decision must depend only on its Assessment and Judgment.');
