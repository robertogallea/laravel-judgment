<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Exceptions\UnrebuildableAssessment;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\ConstantEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Department;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Flag;
use RobertoGallea\Judgment\Tests\Fixtures\ListingTone;
use RobertoGallea\Judgment\Tests\Fixtures\PostModeration;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\StrictReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\SupportTicket;
use RobertoGallea\Judgment\Tests\Fixtures\Ticket;

it('rebuilds the Assessment from its record', function () {
    $judgment = returnAbuse();
    $judgment->assess();

    $rebuilt = AssessmentRecord::sole()->assessment();

    expect($rebuilt)->toBeInstanceOf(Assessment::class)
        ->and($rebuilt->judgment->subject()?->is($judgment->request))->toBeTrue()
        ->and($rebuilt->likelihood('abusive')->probability())->toBe(.42)
        ->and($rebuilt->classification('department')->label())->toBe(Department::Billing)
        ->and($rebuilt->classification('department')->probabilities())->toBe(['billing' => .70, 'technical' => .20, 'other' => .10])
        ->and($rebuilt->provenance->logContext())->toBe(['engine' => 'fake', 'model' => 'fake-1.0.0', 'request_id' => 'req-1']);
});

it('re-decides a rebuilt Assessment without changing the recorded Outcome', function () {
    returnAbuse()->assess()->outcome();

    $rebuilt = AssessmentRecord::sole()->assessment();

    expect($rebuilt->outcome())->toBe(RefundOutcome::Approve)
        ->and($rebuilt->decide(new StrictReturnDecision))->toBe(RefundOutcome::Reject)
        ->and(AssessmentRecord::sole()->outcome)->toBe('approve');
});

it('rebuilds over the Judgment given, when it is not constructed with a stored Subject', function () {
    assessTicket(['severity' => [.10, .20, .60, .10]]);
    app()->instance(Engine::class, new FakeEngine(['flags.hate' => .05, 'flags.spam' => .91, 'flags.self_harm' => .02, 'topics.politics' => .30, 'topics.sport' => .64]));
    (new PostModeration('Buy followers now!'))->assess();

    [$ticket, $post] = AssessmentRecord::orderBy('id')->get()->all();
    $ticket = $ticket->assessment(new SupportTicket(new Ticket('Doppio addebito', 'Mi avete addebitato due volte.')));
    $post = $post->assessment(new PostModeration('Buy followers now!'));

    expect($ticket->rating('severity')->probabilities())->toBe([.10, .20, .60, .10])
        ->and($ticket->rating('severity')->level())->toBe(2)
        ->and($post->likelihoodSet('flags')->labelsAbove(.5))->toBe([Flag::Spam])
        ->and($post->likelihoodSet('topics')->of('sport')->probability())->toBe(.64);
});

it('refuses to rebuild over another Judgment', function () {
    returnAbuse()->assess();
    $record = AssessmentRecord::sole();

    expect(fn () => $record->assessment(new ListingTone('Best jacket ever!!!')))
        ->toThrow(UnrebuildableAssessment::class, 'recorded for '.ReturnAbuse::class.', not '.ListingTone::class);
});

it('refuses to rebuild once the Questions have changed', function () {
    app()->instance(Engine::class, new ConstantEngine);
    (new ListingTone('Best jacket ever!!!'))->assess();
    $record = AssessmentRecord::sole();

    expect($record->assessment(new ListingTone('Best jacket ever!!!'))->likelihood('hyped')->probability())->toBe(.5)
        ->and(fn () => $record->assessment(new ListingTone('Best jacket ever!!!', 'Is the listing title exaggerated?')))
        ->toThrow(UnrebuildableAssessment::class, 'Questions of '.ListingTone::class.' have changed');
});

it('refuses to rebuild without a Subject to construct the Judgment with', function () {
    assessTicket();

    expect(fn () => AssessmentRecord::sole()->assessment())
        ->toThrow(UnrebuildableAssessment::class, 'no Subject to construct '.SupportTicket::class.' with: pass the Judgment');
});
