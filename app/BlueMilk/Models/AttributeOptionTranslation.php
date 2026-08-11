<?php

namespace App\BlueMilk\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeOptionTranslation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function attribute_option()
    {
        return $this->belongsTo(AttributeOption::class);
    }
}
