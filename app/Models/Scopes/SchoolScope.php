<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class SchoolScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Si hay un usuario logueado y tiene un colegio ACTIVO, filtramos.
        if (Auth::hasUser() && Auth::user()->current_school_id) {
            $builder->where($model->getTable() . '.school_id', Auth::user()->current_school_id);
        }
    }
}
