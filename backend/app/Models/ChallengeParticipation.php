<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's entry into a challenge occurrence, linked to the normal exam
 * attempt that holds the answers, timer, and authoritative score.
 *
 * Reward settlement (gamification ledger + optional wallet prize) is written
 * at most once per participation and at most once per occurrence per user.
 */
class ChallengeParticipation extends Model
{
    protected $table = 'challenge_participations';

    protected $fillable = [
        'challenge_id',
        'user_id',
        'attempt_id',
        'occurrence_key',
        'attempt_index',
        'points_awarded',
        'xp_awarded',
        'wallet_minor_awarded',
        'wallet_transaction_id',
        'rewarded',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_index' => 'integer',
            'points_awarded' => 'integer',
            'xp_awarded' => 'integer',
            'wallet_minor_awarded' => 'integer',
            'rewarded' => 'boolean',
            'settled_at' => 'datetime',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
