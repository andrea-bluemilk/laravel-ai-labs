<?php

namespace App\Models;

use App\Enums\CheckinStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $casts = [
        'called_at' => 'datetime',
        'status' => CheckinStatus::class,
    ];

    public function security_guard(): BelongsTo
    {
        return $this->belongsTo(SecurityGuard::class);
    }
}
