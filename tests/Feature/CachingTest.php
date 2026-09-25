<?php

use Illuminate\Support\Facades\Event;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineManager;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Evidence;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\CachedListingTone;
use RobertoGallea\Judgment\Tests\Fixtures\ConstantEngine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ListingTone;

/** A FakeEngine answering every listing as hyped with the given probability, on the given model. */
function listingEngine(float $hyped = .8, string $model = 'fake-1.0.0'): FakeEngine
{
    $engine = new FakeEngine(['hyped' => $hyped], $model);
    app()->instance(Engine::class, $engine);

    return $engine;
}

it('asks the Engine every time unless the Judgment opts into caching', function () {
    $engine = listingEngine();

    (new ListingTone('Best jacket ever!!!'))->assess();
    (new ListingTone('Best jacket ever!!!'))->assess();

    expect($engine->requests)->toHaveCount(2);
});

it('reuses the Assessment of identical Evidence and Questions while a Judgment is cached', function () {
    $engine = listingEngine(.8);
    $first = (new CachedListingTone('Best jacket ever!!!'))->assess();

    listingEngine(.1);
    $again = (new CachedListingTone('Best jacket ever!!!'))->assess();

    expect($engine->requests)->toHaveCount(1)
        ->and($again->likelihood('hyped')->probability())->toBe(.8)
        ->and($again->provenance->logContext())->toBe($first->provenance->logContext());
});

it('records a cache hit as a new record pointing at the original, with its Provenance', function () {
    listingEngine();
    (new CachedListingTone('Best jacket ever!!!'))->assess();
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    [$original, $hit] = AssessmentRecord::orderBy('id')->get()->all();

    expect($original->cached_from_id)->toBeNull()
        ->and($hit->cached_from_id)->toBe($original->id)
        ->and($hit->cachedFrom?->is($original))->toBeTrue()
        ->and($hit->only('engine', 'model', 'request_id', 'answers'))->toBe($original->only('engine', 'model', 'request_id', 'answers'));
});

it('keeps the Engine\'s usage and raw response on the original only, as a hit spends nothing', function () {
    app()->instance(Engine::class, new FakeEngine(['hyped' => .8], details: ['usage' => ['tokens' => 120]]));
    $first = (new CachedListingTone('Best jacket ever!!!'))->assess();
    $again = (new CachedListingTone('Best jacket ever!!!'))->assess();

    [$original, $hit] = AssessmentRecord::orderBy('id')->get()->all();

    expect($first->provenance->details)->toBe(['usage' => ['tokens' => 120]])
        ->and($original->provenance_details)->toBe(['usage' => ['tokens' => 120]])
        ->and($again->provenance->details)->toBe([])
        ->and($hit->provenance_details)->toBe([]);
});

it('asks the Engine again rather than store a hit with no original to point at', function () {
    config(['judgment.persistence.enabled' => false]);
    listingEngine(.8);
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    config(['judgment.persistence.enabled' => true]);
    $engine = listingEngine(.1);
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    expect($engine->requests)->toHaveCount(1)
        ->and(AssessmentRecord::sole()->cached_from_id)->toBeNull();
});

it('announces a cache hit with its own record', function () {
    listingEngine();
    (new CachedListingTone('Best jacket ever!!!'))->assess();
    Event::fake([AssessmentCompleted::class]);

    (new CachedListingTone('Best jacket ever!!!'))->assess();

    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->record?->cached_from_id !== null);
});

it('reuses cached Assessments when persistence is off', function () {
    config(['judgment.persistence.enabled' => false]);
    $engine = listingEngine();

    (new CachedListingTone('Best jacket ever!!!'))->assess();
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    expect($engine->requests)->toHaveCount(1)
        ->and(AssessmentRecord::count())->toBe(0);
});

it('asks the Engine again when anything in the cache key differs', function (string $difference) {
    listingEngine(.8);
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    $engine = listingEngine(.1, $difference === 'another pinned model' ? 'fake-1.1.0' : 'fake-1.0.0');
    if ($difference === 'another Engine on the same model') {
        listingEngine(.8, 'constant-1');
        (new CachedListingTone('Best jacket ever!!!'))->assess();
        app()->instance(Engine::class, $engine = new ConstantEngine);
    }
    match ($difference) {
        'other Evidence' => (new CachedListingTone('A warm jacket.'))->assess(),
        'a reworded Question' => (new CachedListingTone('Best jacket ever!!!', 'Does the listing title oversell the item?'))->assess(),
        'another pinned model', 'another Engine on the same model' => (new CachedListingTone('Best jacket ever!!!'))->assess(),
        'the same text marked untrusted' => (new CachedListingTone(Evidence::untrusted('Best jacket ever!!!')))->assess(),
        'another Judgment' => (new class('Best jacket ever!!!') extends CachedListingTone {})->assess(),
    };

    expect($engine->requests)->toHaveCount(1);
})->with(['other Evidence', 'a reworded Question', 'another pinned model', 'another Judgment', 'another Engine on the same model', 'the same text marked untrusted']);

it('asks the Engine again on another connection serving the same model name', function () {
    $engines = ['here' => new FakeEngine(['hyped' => .8], 'english'), 'there' => new FakeEngine(['hyped' => .1], 'english')];
    app(EngineManager::class)->extend('fake', fn ($app, array $config, string $connection) => $engines[$connection]);
    config(['judgment.engines.here' => ['driver' => 'fake'], 'judgment.engines.there' => ['driver' => 'fake']]);
    $on = fn (string $connection) => new class('Best jacket ever!!!', $connection) extends CachedListingTone
    {
        public function __construct(string $title, private readonly string $connection)
        {
            parent::__construct($title);
        }

        public function engine(): ?string
        {
            return $this->connection;
        }
    };

    $on('here')->assess();
    $there = $on('there')->assess();

    expect($engines['there']->requests)->toHaveCount(1)
        ->and($there->likelihood('hyped')->probability())->toBe(.1);
});

it('asks the Engine again once the cached Assessment expires', function () {
    listingEngine(.8);
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    $this->travel(3601)->seconds();
    $engine = listingEngine(.1);
    $again = (new CachedListingTone('Best jacket ever!!!'))->assess();

    expect($engine->requests)->toHaveCount(1)
        ->and($again->likelihood('hyped')->probability())->toBe(.1);
});
