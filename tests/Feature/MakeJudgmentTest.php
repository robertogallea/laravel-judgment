<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;

afterEach(fn () => File::deleteDirectory(app_path('Judgments')));

it('generates a Judgment constructed with its Subject', function () {
    $name = 'RefundAbuse'.Str::random(8);

    $this->artisan('make:judgment', ['name' => $name, '--subject' => 'Refund'])->assertSuccessful();

    $judgment = generatedClass(app_path("Judgments/{$name}.php"), "App\\Judgments\\{$name}");
    $subject = $judgment->getConstructor()?->getParameters()[0];

    expect($judgment->isSubclassOf(Judgment::class))->toBeTrue()
        ->and((string) $subject?->getType())->toBe('App\Models\Refund')
        ->and($subject?->getName())->toBe('refund')
        ->and($judgment->getMethod('evidence')->class)->toBe($judgment->name)
        ->and($judgment->getMethod('questions')->class)->toBe($judgment->name)
        ->and($judgment->getMethod('decision')->class)->toBe($judgment->name);
});

it('accepts a fully qualified Subject class', function () {
    $name = 'RefundAbuse'.Str::random(8);

    $this->artisan('make:judgment', ['name' => $name, '--subject' => Refund::class])->assertSuccessful();

    $judgment = generatedClass(app_path("Judgments/{$name}.php"), "App\\Judgments\\{$name}");

    expect((string) $judgment->getConstructor()?->getParameters()[0]->getType())->toBe(Refund::class);
});

it('generates a Judgment over any Subject when none is given', function () {
    $name = 'RefundAbuse'.Str::random(8);

    $this->artisan('make:judgment', ['name' => $name])->assertSuccessful();

    $judgment = generatedClass(app_path("Judgments/{$name}.php"), "App\\Judgments\\{$name}");
    $subject = $judgment->getConstructor()?->getParameters()[0];

    expect((string) $subject?->getType())->toBe('mixed')
        ->and($subject?->getName())->toBe('subject');
});

it('compiles when the Subject shares a name with the Judgment base class', function () {
    $name = 'RefundAbuse'.Str::random(8);

    $this->artisan('make:judgment', ['name' => $name, '--subject' => 'Judgment'])->assertSuccessful();

    $judgment = generatedClass(app_path("Judgments/{$name}.php"), "App\\Judgments\\{$name}");

    expect((string) $judgment->getConstructor()?->getParameters()[0]->getType())->toBe('App\Models\Judgment');
});
