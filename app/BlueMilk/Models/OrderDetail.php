<?php

namespace App\BlueMilk\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function buyable()
    {
        return $this->morphTo();
    }
}
