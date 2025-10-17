<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ClassRoutine extends Model
{
    protected $fillable = [
        'course_id',
        'batch_id',
        'days',
        'off_dates',
    ];

    protected $casts = [
        'days' => 'array',
        'off_dates' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function isClassToday(): bool
    {
        $today = Carbon::now()->format('l');

        foreach ($this->days as $day) {
            if ($day['day'] === $today) {
                return !$this->isOffToday();
            }
        }

        return false;
    }

    public function isOffToday(): bool
    {
        $today = Carbon::now()->format('Y-m-d');

        if ($this->off_dates) {
            foreach ($this->off_dates as $offDate) {
                if ($offDate['date'] === $today) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getTodayClassTime(): ?array
    {
        $today = Carbon::now()->format('l');

        foreach ($this->days as $day) {
            if ($day['day'] === $today) {
                return $day;
            }
        }

        return null;
    }
}
