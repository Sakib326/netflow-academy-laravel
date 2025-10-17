<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'course_id',
        'status',
        'amount',
        'discount_amount',
        'coupon_id',          // Add this
        'coupon_discount',    // Add this
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'coupon_discount' => 'decimal:2', // Add this
    ];

    // Auto-generate order number (KEEP AS IS)
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->order_number = 'ORD-' . strtoupper(uniqid());
        });
    }

    // EXISTING Relationships (KEEP AS IS)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function enrollment()
    {
        return $this->hasOne(Enrollment::class);
    }

    // NEW: Add coupon relationship
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    // EXISTING Status helpers (KEEP AS IS - DO NOT CHANGE)
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // NEW: Coupon-related methods (won't break existing code)
    public function applyCoupon(Coupon $coupon): bool
    {
        if (!$coupon->isValidForCourse($this->course_id, $this->amount)) {
            return false;
        }

        $discountAmount = $coupon->getDiscountAmount($this->amount);

        $this->coupon_id = $coupon->id;
        $this->coupon_discount = $discountAmount;

        return true;
    }

    public function removeCoupon(): void
    {
        $this->coupon_id = null;
        $this->coupon_discount = 0;
    }

    public function hasCoupon(): bool
    {
        return !is_null($this->coupon_id);
    }

    // NEW: Amount calculation methods (optional - won't break existing)
    public function getTotalDiscount(): float
    {
        return ($this->discount_amount ?? 0) + ($this->coupon_discount ?? 0);
    }

    public function getFinalAmount(): float
    {
        return max(0, $this->amount - $this->getTotalDiscount());
    }

    // NEW: Get base amount (for clarity)
    public function getBaseAmount(): float
    {
        return $this->amount;
    }
}
