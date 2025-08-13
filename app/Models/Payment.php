<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'course_id', 'amount', 'status', 
        'payment_method', 'transaction_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // Helper Functions
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function isRefunded()
    {
        return $this->status === 'refunded';
    }

    public function markAsCompleted($transactionId = null)
    {
        $this->update([
            'status' => 'completed',
            'transaction_id' => $transactionId ?? $this->transaction_id
        ]);
    }

    public function markAsFailed()
    {
        $this->update(['status' => 'failed']);
    }
}