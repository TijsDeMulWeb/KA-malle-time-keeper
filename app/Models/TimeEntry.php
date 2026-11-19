<?php

namespace App\Models;

use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property int|null $category_id
 * @property string $description
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['user_id', 'project_id', 'category_id', 'description', 'started_at', 'ended_at'])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function scopeFinished(Builder $query): void
    {
        $query->whereNotNull('ended_at');
    }

    public function scopeStartingBetween(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->where('started_at', '>=', $from)->where('started_at', '<', $until);
    }

    public function scopeOverlapping(Builder $query, CarbonInterface $start, ?CarbonInterface $end): void
    {
        if ($end) {
            $query->where('started_at', '<', $end);
        }

        $query->where(function (Builder $query) use ($start) {
            $query->whereNull('ended_at')->orWhere('ended_at', '>', $start);
        });
    }

    public function isLocked(): bool
    {
        return SchoolYear::isLockedAt($this->started_at);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function durationInMinutes(): int
    {
        $end = $this->ended_at ?? now();

        return (int) $this->started_at->diffInMinutes($end, absolute: true);
    }

    public function minutesWithin(CarbonInterface $from, CarbonInterface $until): int
    {
        $start = $this->started_at->greaterThan($from) ? $this->started_at : $from;
        $end = $this->ended_at ?? now();
        $end = $end->lessThan($until) ? $end : $until;

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return (int) $start->diffInMinutes($end);
    }

    public function localStartedAt(): CarbonImmutable
    {
        return LocalTime::local($this->started_at);
    }

    public function localEndedAt(): ?CarbonImmutable
    {
        return $this->ended_at ? LocalTime::local($this->ended_at) : null;
    }
}
