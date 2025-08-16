<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResponse extends Model
{
    use HasFactory;

    protected $table = 'exam_response';

    protected $fillable = [
        'exam_id',
        'batch_id',
        'user_id',
        'content',
        'total_time_taken',
        'score',
        'max_score',
        'percentage',
        'status',
        'started_at',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'content' => 'array',  // JSON column
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /* ---------------- Helpers ---------------- */

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isGraded(): bool
    {
        return $this->status === 'graded';
    }

    public function remainingTime(): ?int
    {
        if (!$this->exam) {
            return null;
        }
        return max(0, $this->exam->total_time - ($this->total_time_taken ?? 0));
    }

    public function scorePercentage(): ?float
    {
        if ($this->max_score > 0) {
            return round(($this->score / $this->max_score) * 100, 2);
        }
        return null;
    }
}
