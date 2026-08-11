<?php

namespace App\BlueMilk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class GroupTranslation extends Model
{
    use HasSlug;

    protected $guarded = [];

    public $timestamps = false;

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }
}
