<?php

namespace App\BlueMilk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class FamilyTranslation extends Model
{
    use HasSlug;

    protected $guarded = [];

    public $timestamps = false;

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }
}
