<?php

namespace App\Models;

use Database\Factories\BeaconEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $major
 * @property int $minor
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['major', 'minor', 'type'])]
class BeaconEvent extends Model
{
    /** @use HasFactory<BeaconEventFactory> */
    use HasFactory;

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
        ];
    }
}
