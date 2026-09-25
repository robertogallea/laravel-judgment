<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
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

function refundAbuse(): RefundAbuse
{
    return new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.'));
}

/**
 * Lint and load a generated class, returning its reflection.
 *
 * @param  class-string  $class
 * @return ReflectionClass<object>
 */
function generatedClass(string $path, string $class): ReflectionClass
{
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $status);
    expect($status)->toBe(0, implode("\n", $output));

    require_once $path;

    return new ReflectionClass($class);
}
