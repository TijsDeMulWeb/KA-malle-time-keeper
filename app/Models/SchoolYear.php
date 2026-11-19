<?php

namespace App\Models;

use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $closed_at
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'closed_at'])]
class SchoolYear extends Model
{
    /** @use HasFactory<SchoolYearFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function projects(): HasManyThrough
    {
        return $this->hasManyThrough(Project::class, SchoolClass::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    public function scopeClosed(Builder $query): void
    {
        $query->whereNotNull('closed_at');
    }

    public function scopeCovering(Builder $query, CarbonInterface $moment): void
    {
        $date = LocalTime::local($moment)->format('Y-m-d');

        $query->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    public static function isLockedAt(CarbonInterface $moment): bool
    {
        return self::closed()->covering($moment)->exists();
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function startsAt(): CarbonImmutable
    {
        return LocalTime::parse($this->starts_on->format('Y-m-d'));
    }

    public function endsAt(): CarbonImmutable
    {
        return LocalTime::parse($this->ends_on->format('Y-m-d'))->addDay();
    }
}
