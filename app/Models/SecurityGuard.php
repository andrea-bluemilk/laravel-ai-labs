<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityGuard extends Model
{
    protected $guarded = [];

    public function checkins(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Checkin::class);
    }
}
