<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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
 * @property bool $is_live
 */
#[Fillable(['user_id', 'project_id', 'category_id', 'description', 'started_at', 'ended_at', 'is_live'])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_live' => 'boolean',
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

    public function scopeStartingBetween(Builder $query, Carbon $from, Carbon $until): void
    {
        $query->where('started_at', '>=', $from)->where('started_at', '<', $until);
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

    public function localStartedAt(): CarbonImmutable
    {
        return $this->started_at->toImmutable()->setTimezone(config('time-keeper.timezone'));
    }

    public function localEndedAt(): ?CarbonImmutable
    {
        return $this->ended_at?->toImmutable()->setTimezone(config('time-keeper.timezone'));
    }
}
