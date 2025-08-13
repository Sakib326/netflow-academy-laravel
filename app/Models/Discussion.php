<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discussion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'discussable_type', 'discussable_id', 'parent_id',
        'title', 'content', 'is_question', 'is_answered', 'upvotes'
    ];

    protected $casts = [
        'is_question' => 'boolean',
        'is_answered' => 'boolean'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function discussable()
    {
        return $this->morphTo();
    }

    public function parent()
    {
        return $this->belongsTo(Discussion::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Discussion::class, 'parent_id')->orderBy('created_at');
    }

    public function allReplies()
    {
        return $this->hasMany(Discussion::class, 'parent_id')->with('allReplies');
    }

    // Scopes
    public function scopeQuestions($query)
    {
        return $query->where('is_question', true);
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    // Helper Functions
    public function isReply()
    {
        return !is_null($this->parent_id);
    }

    public function getRepliesCount()
    {
        return $this->replies()->count();
    }

    public function markAsAnswered()
    {
        if ($this->is_question) {
            $this->update(['is_answered' => true]);
        }
    }

    public function toggleUpvote($userId)
    {
        // This is a simple implementation
        // In production, you'd want a separate upvotes table
        $this->increment('upvotes');
    }
}