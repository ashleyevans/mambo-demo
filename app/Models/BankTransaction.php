<?php

namespace App\Models;

use Database\Factories\BankTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $bank_account_id
 * @property string $truelayer_transaction_id
 * @property string $status
 * @property string|null $normalised_provider_transaction_id
 * @property string|null $provider_transaction_id
 * @property Carbon|null $booked_at
 * @property string|null $description
 * @property string|null $amount
 * @property string|null $currency
 * @property string|null $transaction_type
 * @property string|null $transaction_category
 * @property array<int, string>|null $transaction_classification
 * @property string|null $merchant_name
 * @property string|null $running_balance
 * @property string|null $running_balance_currency
 * @property array<string, mixed>|null $meta
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'bank_account_id', 'truelayer_transaction_id', 'status', 'normalised_provider_transaction_id', 'provider_transaction_id', 'booked_at', 'description', 'amount', 'currency', 'transaction_type', 'transaction_category', 'transaction_classification', 'merchant_name', 'running_balance', 'running_balance_currency', 'meta', 'raw'])]
class BankTransaction extends Model
{
    /** @use HasFactory<BankTransactionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'transaction_classification' => 'array',
            'meta' => 'array',
            'raw' => 'array',
        ];
    }
}
