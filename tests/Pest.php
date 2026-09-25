<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\SupportTicket;
use RobertoGallea\Judgment\Tests\Fixtures\Ticket;
use RobertoGallea\Judgment\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * Assess a SupportTicket, overriding the scripted answers given.
 *
 * @param  array<string, mixed>  $answers
 */
function assessTicket(array $answers = []): Assessment
{
    app()->instance(Engine::class, new FakeEngine([
        'language' => ['english' => .15, 'italian' => .80, 'other' => .05],
        'department' => ['billing' => .70, 'technical' => .20, 'other' => .10],
        'severity' => [.10, .20, .60, .10],
        ...$answers,
    ]));

    return (new SupportTicket(new Ticket('Doppio addebito', 'Mi avete addebitato due volte.')))->assess();
}
