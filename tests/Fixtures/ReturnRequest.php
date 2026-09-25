<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RobertoGallea\Judgment\Concerns\HasAssessments;

/**
 * @property string $item
 * @property string $reason
 * @property ?string $language
 */
final class ReturnRequest extends Model
{
    use HasAssessments;

    protected $guarded = [];
}
