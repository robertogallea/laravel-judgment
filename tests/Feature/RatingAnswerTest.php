<?php

it('reads the most probable level of a Rating, counting from zero', function () {
    expect(assessTicket(['severity' => [.10, .20, .60, .10]])->rating('severity')->level())->toBe(2);
});

it('reads the expected level, weighing every level by its probability', function () {
    expect(assessTicket(['severity' => [.10, .20, .60, .10]])->rating('severity')->expected())
        ->toEqualWithDelta(1.7, 1e-9);
});

it('exposes the probability of each level, lowest first', function () {
    expect(assessTicket(['severity' => [.10, .20, .60, .10]])->rating('severity')->probabilities())
        ->toBe([.10, .20, .60, .10]);
});

it('is as confident as the margin between the top level and the runner-up', function () {
    expect(assessTicket(['severity' => [.10, .20, .60, .10]])->rating('severity')->confidence())
        ->toEqualWithDelta(.40, 1e-9);
});

it('compares its expected level against thresholds, not its most probable level', function () {
    // Most probable level is 2, but the expected level is 1.7.
    $severity = assessTicket(['severity' => [.10, .20, .60, .10]])->rating('severity');

    expect($severity->above(1.5))->toBeTrue()
        ->and($severity->above(2.0))->toBeFalse()
        ->and($severity->below(2.0))->toBeTrue()
        ->and($severity->below(1.5))->toBeFalse()
        ->and($severity->between(1.5, 2.0))->toBeTrue()
        ->and($severity->between(2.0, 3.0))->toBeFalse();
});
