<?php

namespace App\BlueMilk\Models;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeOption extends Model
{
    use SoftDeletes, Translatable;

    public $translatedAttributes = ['name', 'locale'];

    protected $guarded = [];

    protected $with = ['translations'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
