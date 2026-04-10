<?php

namespace App\Models\Traits;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToSchool
{
    /**
     * Boot the trait and apply the global scope.
     */
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        // Automáticamente asignamos el school_id (el contexto actual) al crear registros
        static::creating(function ($model) {
            if (empty($model->school_id) && Auth::hasUser() && Auth::user()->current_school_id) {
                $model->school_id = Auth::user()->current_school_id;
            }
        });
    }

    /**
     * Get the school that owns the model.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
