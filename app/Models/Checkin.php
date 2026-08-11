<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    protected $guarded = [];
    public $casts = [
        'called_at' => 'datetime',
        'status' => \App\Enums\CheckinStatus::class,
    ];

    public function guard(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guard::class);
    }
}
