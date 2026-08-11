<?php

namespace App\BlueMilk\Models;

use App\BlueMilk\Priority\HasPriority;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use HasPriority, SoftDeletes, Translatable;

    protected $guarded = [];

    protected $with = ['translations'];

    public $translatedAttributes = ['name', 'locale'];

    public function attribute_options(): HasMany
    {
        return $this->hasMany(AttributeOption::class);
    }
}
