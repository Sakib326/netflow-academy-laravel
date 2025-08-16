<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    use HasFactory;

    protected $table = 'exam';

    protected $fillable = [
        'batch_id',
        'course_id',
        'title',
        'description',
        'content',
        'total_time',
        'status',
    ];

    protected $casts = [
        'content' => 'array',   // JSON column
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExamResponse::class, 'exam_id');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /* ---------------- Helpers ---------------- */

    public function averageScore(): ?float
    {
        return $this->responses()->avg('score');
    }

    public function totalAttempts(): int
    {
        return $this->responses()->count();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
