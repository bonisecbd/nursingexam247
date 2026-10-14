<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'in_app_enabled',
        'announcements',
        'rewards',
        'results',
        'offers',
        'reminders',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'announcements' => 'boolean',
            'rewards' => 'boolean',
            'results' => 'boolean',
            'offers' => 'boolean',
            'reminders' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
