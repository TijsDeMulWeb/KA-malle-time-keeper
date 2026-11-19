<?php

namespace App\Models;

use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_year_id
 * @property string $name
 * @property SchoolYear $schoolYear
 */
#[Fillable(['school_year_id', 'name'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scopeInOpenYear(Builder $query): void
    {
        $query->whereHas('schoolYear', fn (Builder $query) => $query->open());
    }

    public function isClosed(): bool
    {
        return $this->schoolYear->isClosed();
    }

    public function fullName(): string
    {
        return "{$this->name} ({$this->schoolYear->name})";
    }
}
