<?php

namespace App\Models;

use Database\Factories\BankConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $provider_id
 * @property string|null $provider_name
 * @property string|null $logo_uri
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $consent_expires_at
 * @property string $status
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'provider_id', 'provider_name', 'logo_uri', 'access_token', 'refresh_token', 'token_expires_at', 'consent_expires_at', 'status', 'last_synced_at'])]
#[Hidden(['access_token', 'refresh_token'])]
class BankConnection extends Model
{
    /** @use HasFactory<BankConnectionFactory> */
    use HasFactory;

    /**
     * Determine whether the stored access token has expired (or is about to).
     */
    public function tokenHasExpired(): bool
    {
        return $this->token_expires_at === null
            || $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Determine whether the user's open banking consent has lapsed.
     */
    public function consentHasExpired(): bool
    {
        return $this->consent_expires_at !== null && $this->consent_expires_at->isPast();
    }

    /**
     * Scope a query to connections we can still query in the background.
     *
     * @param  Builder<BankConnection>  $query
     */
    public function scopeSyncable(Builder $query): void
    {
        $query->where('status', 'active')
            ->whereNotNull('refresh_token')
            ->where(function (Builder $query) {
                $query->whereNull('consent_expires_at')
                    ->orWhere('consent_expires_at', '>', now());
            });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'consent_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
