<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'setting_id',
        'key',
        'actor_id',
        'old_value',
        'new_value',
        'reason',
        'created_at',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
