<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A published daily, weekly, or one-off quiz challenge bound to a test.
 *
 * The overall availability window (starts_at/ends_at, UTC) gates joins;
 * `period` buckets participations into local occurrences (Asia/Dhaka by
 * default) for attempt limits and reward deduplication.
 */
class Challenge extends Model
{
    public const PERIODS = ['daily', 'weekly', 'once'];

    protected $table = 'challenges';

    protected $fillable = [
        'title',
        'description',
        'period',
        'test_id',
        'timezone',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'attempts_allowed',
        'reward_points',
        'reward_xp',
        'reward_wallet_minor',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'attempts_allowed' => 'integer',
            'reward_points' => 'integer',
            'reward_xp' => 'integer',
            'reward_wallet_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(ModelTest::class, 'test_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ChallengeParticipation::class, 'challenge_id');
    }
}
