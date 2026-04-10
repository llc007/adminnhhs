<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'domain'])]
class School extends Model
{
    /**
     * Get the users that belong to this school (many-to-many via school_user).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('roles')->withTimestamps();
    }

    /**
     * Get the academic years for this school.
     */
    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    /**
     * Get all academic terms for this school through academic years.
     */
    public function academicTerms(): HasManyThrough
    {
        return $this->hasManyThrough(AcademicTerm::class, AcademicYear::class);
    }

    /**
     * Get all cursos for this school.
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }
}
