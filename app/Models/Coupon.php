<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'minimum_amount',
        'usage_limit',
        'used_count',
        'is_active',
        'expires_at',
        'course_ids',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'course_ids' => 'array',
    ];

    // Auto-format code on creation
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($coupon) {
            $coupon->code = strtoupper($coupon->code);
        });

        static::updating(function ($coupon) {
            $coupon->code = strtoupper($coupon->code);
        });
    }

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function courses()
    {
        if ($this->course_ids && !empty($this->course_ids)) {
            return Course::whereIn('id', $this->course_ids);
        }
        return Course::query(); // All courses if no specific ones
    }

    // Validation Methods
    public function isValid($orderAmount = 0): bool
    {
        return $this->isActive() &&
               $this->isNotExpired() &&
               $this->hasUsageRemaining() &&
               $this->meetsMinimumAmount($orderAmount);
    }

    public function isValidForCourse($courseId, $orderAmount = 0): bool
    {
        return $this->isValid($orderAmount) && $this->appliesToCourse($courseId);
    }

    // Individual validation checks
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isNotExpired(): bool
    {
        return !$this->expires_at || $this->expires_at->isFuture();
    }

    public function hasUsageRemaining(): bool
    {
        return !$this->usage_limit || $this->used_count < $this->usage_limit;
    }

    public function meetsMinimumAmount($amount): bool
    {
        return !$this->minimum_amount || $amount >= $this->minimum_amount;
    }

    public function appliesToCourse($courseId): bool
    {
        return $this->appliesToAllCourses() || in_array($courseId, $this->course_ids ?? []);
    }

    public function appliesToAllCourses(): bool
    {
        return empty($this->course_ids);
    }

    // Discount calculation
    public function getDiscountAmount($orderAmount): float
    {
        if (!$this->isValid($orderAmount)) {
            return 0;
        }

        if ($this->type === 'fixed') {
            return min($this->value, $orderAmount);
        }

        if ($this->type === 'percentage') {
            return ($orderAmount * $this->value) / 100;
        }

        return 0;
    }

    public function getFinalAmount($orderAmount): float
    {
        return max(0, $orderAmount - $this->getDiscountAmount($orderAmount));
    }

    // Usage tracking
    public function markAsUsed(): void
    {
        $this->increment('used_count');
    }

    public function getRemainingUsage(): ?int
    {
        if (!$this->usage_limit) {
            return null; // Unlimited
        }
        return max(0, $this->usage_limit - $this->used_count);
    }

    // Course management
    public function addCourseId($courseId): void
    {
        $courseIds = $this->course_ids ?? [];
        if (!in_array($courseId, $courseIds)) {
            $courseIds[] = $courseId;
            $this->course_ids = $courseIds;
        }
    }

    public function removeCourseId($courseId): void
    {
        $courseIds = $this->course_ids ?? [];
        $this->course_ids = array_values(array_filter($courseIds, fn ($id) => $id != $courseId));
    }

    public function hasCourseId($courseId): bool
    {
        return in_array($courseId, $this->course_ids ?? []);
    }

    public function setCourseIds(array $courseIds): void
    {
        $this->course_ids = array_unique($courseIds);
    }

    // Validation messages
    public function getValidationMessage($orderAmount = 0, $courseId = null): string
    {
        if (!$this->isActive()) {
            return 'Coupon is inactive';
        }

        if (!$this->isNotExpired()) {
            return 'Coupon has expired';
        }

        if (!$this->hasUsageRemaining()) {
            return 'Coupon usage limit reached';
        }

        if (!$this->meetsMinimumAmount($orderAmount)) {
            return "Minimum order amount is {$this->minimum_amount}";
        }

        if ($courseId && !$this->appliesToCourse($courseId)) {
            return 'Coupon is not valid for this course';
        }

        return 'Coupon applied successfully';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeHasUsageRemaining($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('usage_limit')
              ->orWhereColumn('used_count', '<', 'usage_limit');
        });
    }

    public function scopeForCourse($query, $courseId)
    {
        return $query->where(function ($q) use ($courseId) {
            $q->whereNull('course_ids')
              ->orWhereJsonContains('course_ids', $courseId);
        });
    }

    // Helper attributes
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getUsagePercentageAttribute(): float
    {
        if (!$this->usage_limit) {
            return 0;
        }
        return ($this->used_count / $this->usage_limit) * 100;
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return max(0, now()->diffInDays($this->expires_at, false));
    }
}
