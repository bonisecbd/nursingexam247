<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageEntitlement extends Model
{
    /** Configuration rows are immutable and only ever created with a timestamp. */
    public $timestamps = false;

    protected $fillable = [
        'package_id',
        'entitlement_type',
        'test_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(ModelTest::class, 'test_id');
    }
}
