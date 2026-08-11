<?php

namespace App\BlueMilk\Models;

use Illuminate\Database\Eloquent\Model;

class SkuTranslation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }
}
