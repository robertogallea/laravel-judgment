<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

function boostPath(string $path = ''): string
{
    return __DIR__.'/../../resources/boost'.($path === '' ? '' : "/{$path}");
}

/** @return array<string, string> path => contents of every Markdown and Blade file shipped for Laravel Boost */
function boostResources(): array
{
    $files = [];
    foreach (File::allFiles(boostPath()) as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

it('ships each skill with a name matching its directory and a description', function () {
    $skills = File::directories(boostPath('skills'));

    expect($skills)->not->toBeEmpty();
    foreach ($skills as $skill) {
        $contents = File::get("{$skill}/SKILL.md");

        expect($contents)->toMatch('/\A---\n.*?\n---\n/s')
            ->and(preg_match('/^name: (.+)$/m', $contents, $name))->toBe(1)
            ->and($name[1])->toBe(basename($skill))
            ->and($contents)->toMatch('/^description: \S.+$/m');
    }
});

it('ships a guideline that renders through Blade unchanged', function () {
    $guideline = File::get(boostPath('guidelines/core.blade.php'));

    expect(Blade::render($guideline))->toBe($guideline);
});

it('links only to reference files that exist', function () {
    foreach (boostResources() as $path => $contents) {
        preg_match_all('/\]\((?!https?:)([^)#]+)\)/', $contents, $links);

        foreach ($links[1] as $link) {
            expect(File::exists(boostPath(dirname($path).'/'.$link)))->toBeTrue("{$path} links to missing {$link}");
        }
    }
});

it('mentions only artisan commands and options that exist', function () {
    $commands = Artisan::all();

    foreach (boostResources() as $path => $contents) {
        preg_match_all('/(?:php artisan |`)((?:make|judgment):[\w-]+)/', $contents, $mentions);
        $mentioned = array_unique($mentions[1]);

        foreach ($mentioned as $name) {
            expect(array_key_exists($name, $commands))->toBeTrue("{$path} mentions unknown command {$name}");
        }

        // An option may sit on a later line than its command, so it must belong to one the file mentions.
        preg_match_all('/(?<![\w-])--([a-z][\w-]*)/', $contents, $options);
        foreach (array_unique($options[1]) as $option) {
            $known = array_filter($mentioned, fn (string $name) => $commands[$name]->getDefinition()->hasOption($option));

            expect($known)->not->toBeEmpty("{$path} mentions --{$option}, an option of no command it mentions");
        }
    }
});
