<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

/**
 * @property string $name
 * @property bool $can_resolve
 */
final class Reviewer extends User
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['can_resolve' => 'boolean'];
    }
}
