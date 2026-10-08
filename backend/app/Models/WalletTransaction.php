<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    /** Append-only ledger: only created_at is meaningful. */
    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'type',
        'amount_minor',
        'currency',
        'balance_after_minor',
        'source_type',
        'source_id',
        'status',
        'idempotency_key',
        'reverses_transaction_id',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
