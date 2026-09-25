<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;

afterEach(fn () => File::deleteDirectory(app_path('Decisions')));

it('generates a Decision from a given Judgment to a given Outcome', function () {
    $name = 'RefundDecision'.Str::random(8);

    $this->artisan('make:decision', ['name' => $name, '--judgment' => 'RefundAbuse', '--outcome' => 'RefundOutcome'])
        ->assertSuccessful();

    $decision = generatedClass(app_path("Decisions/{$name}.php"), "App\\Decisions\\{$name}");
    $invoke = $decision->getMethod('__invoke');

    expect($decision->implementsInterface(Decision::class))->toBeTrue()
        ->and((string) $invoke->getParameters()[1]->getType())->toBe('App\Judgments\RefundAbuse')
        ->and((string) $invoke->getReturnType())->toBe('App\Enums\RefundOutcome');
});

it('leaves thresholds as placeholders to calibrate with judgment:eval', function () {
    $name = 'RefundDecision'.Str::random(8);

    $this->artisan('make:decision', ['name' => $name])->assertSuccessful();

    $decision = generatedClass(app_path("Decisions/{$name}.php"), "App\\Decisions\\{$name}");
    $source = File::get((string) $decision->getFileName());
    $numbers = collect(PhpToken::tokenize($source))
        ->filter(fn (PhpToken $token) => $token->is([T_LNUMBER, T_DNUMBER])
            || ($token->is([T_COMMENT, T_DOC_COMMENT]) && preg_match('/\d/', $token->text)));

    expect($numbers)->toBeEmpty()
        ->and((string) $decision->getMethod('__invoke')->getReturnType())->toBe(Outcome::class)
        ->and($source)->toContain('php artisan judgment:eval');
});

it('refuses to decide until it is calibrated', function () {
    $name = 'RefundDecision'.Str::random(8);

    $this->artisan('make:decision', ['name' => $name, '--judgment' => RefundAbuse::class, '--outcome' => RefundOutcome::class])
        ->assertSuccessful();

    $decision = generatedClass(app_path("Decisions/{$name}.php"), "App\\Decisions\\{$name}")->newInstance();
    app()->instance(Engine::class, new FakeEngine(['abusive' => .80]));

    refundAbuse()->assess()->decide($decision);
})->throws(LogicException::class, 'is not calibrated yet: run php artisan judgment:eval.');

it('compiles when a class shares a name with one the Decision imports', function (array $options) {
    $name = 'RefundAbuse';

    $this->artisan('make:decision', ['name' => $name, ...$options])->assertSuccessful();

    generatedClass(app_path("Decisions/{$name}.php"), "App\\Decisions\\{$name}");
})->with([
    'the Judgment named like the Decision' => [['--judgment' => 'RefundAbuse']],
    'an Outcome named Decision' => [['--outcome' => 'Decision']],
]);
