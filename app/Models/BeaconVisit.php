<?php

namespace App\Models;

use Database\Factories\BeaconVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $major
 * @property int $minor
 * @property Carbon $entered_at
 * @property Carbon|null $exited_at
 * @property int|null $duration_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['major', 'minor', 'entered_at', 'exited_at', 'duration_seconds'])]
class BeaconVisit extends Model
{
    /** @use HasFactory<BeaconVisitFactory> */
    use HasFactory;

    /**
     * Scope to visits that have not been closed by an exit yet.
     *
     * @param  Builder<BeaconVisit>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('exited_at');
    }

    /**
     * Whether the device is still inside the beacon's range.
     */
    public function isOngoing(): bool
    {
        return $this->exited_at === null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'major' => 'integer',
            'minor' => 'integer',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }
}
