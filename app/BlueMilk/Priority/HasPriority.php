<?php

namespace App\BlueMilk\Priority;

// Deps
use Illuminate\Database\Eloquent\Model;

/**
 * Mixin accessor methods, callbacks, and the duplicate() helper into models.
 */
trait HasPriority
{
    /**
     * Boot the trait.
     */
    protected static function bootHasPriority()
    {
        static::creating(function ($model) {
            $model->priority = $model->generatePriorityOnCreate();
        });

    }

    /**
     * Handle adding slug on model creation.
     */
    protected function generatePriorityOnCreate()
    {
        $priority = static::max('priority');

        return $priority ? ++$priority : 0;
    }
}
