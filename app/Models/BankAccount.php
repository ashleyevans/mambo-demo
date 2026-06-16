<?php

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $bank_connection_id
 * @property string $truelayer_account_id
 * @property string|null $display_name
 * @property string|null $account_type
 * @property string|null $currency
 * @property string|null $account_number
 * @property string|null $sort_code
 * @property string|null $iban
 * @property string|null $current_balance
 * @property string|null $available_balance
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'bank_connection_id', 'truelayer_account_id', 'display_name', 'account_type', 'currency', 'account_number', 'sort_code', 'iban', 'current_balance', 'available_balance', 'last_synced_at'])]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BankConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    /**
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }
}
