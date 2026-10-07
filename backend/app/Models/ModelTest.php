<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ModelTest extends Model
{
    protected $table = 'tests';

    protected $fillable = [
        'title',
        'code',
        'description',
        'subject_id',
        'duration_minutes',
        'question_count',
        'total_marks',
        'passing_score',
        'negative_marking',
        'is_negative_marking_enabled',
        'is_premium',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'question_count' => 'integer',
            'total_marks' => 'decimal:2',
            'passing_score' => 'decimal:2',
            'negative_marking' => 'decimal:2',
            'is_negative_marking_enabled' => 'boolean',
            'is_premium' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'test_questions', 'test_id', 'question_id')
            ->withPivot(['sequence', 'points'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }
}
