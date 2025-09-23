<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "CourseList",
    title: "Course List Item",
    description: "Course information for list view",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "title", type: "string", example: "Web Development Fundamentals"),
        new OA\Property(property: "slug", type: "string", example: "web-development-fundamentals"),
        new OA\Property(property: "description", type: "string", example: "Learn the basics of web development"),
        new OA\Property(property: "thumbnail", type: "string", nullable: true, example: "http://example.com/storage/thumbnails/course1.jpg"),
        new OA\Property(property: "price", type: "number", format: "float", example: 99.99),
        new OA\Property(property: "discounted_price", type: "number", format: "float", nullable: true, example: 79.99),
        new OA\Property(property: "duration", type: "string", example: "8 weeks"),
        new OA\Property(property: "level", type: "string", enum: ["beginner", "intermediate", "advanced"], example: "beginner"),
        new OA\Property(property: "language", type: "string", example: "English"),
        new OA\Property(property: "status", type: "string", example: "published"),
        new OA\Property(property: "total_lessons", type: "integer", example: 25),
        new OA\Property(property: "total_students", type: "integer", example: 150),
        new OA\Property(property: "average_rating", type: "number", format: "float", example: 4.5),
        new OA\Property(property: "total_reviews", type: "integer", example: 45),
        new OA\Property(
            property: "category",
            properties: [
                new OA\Property(property: "id", type: "integer", example: 1),
                new OA\Property(property: "name", type: "string", example: "Web Development"),
                new OA\Property(property: "slug", type: "string", example: "web-development"),
            ]
        ),
        new OA\Property(
            property: "instructor",
            properties: [
                new OA\Property(property: "id", type: "integer", example: 1),
                new OA\Property(property: "name", type: "string", example: "John Instructor"),
                new OA\Property(property: "avatar", type: "string", nullable: true, example: "http://example.com/storage/avatars/instructor1.jpg"),
            ]
        ),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "CourseDetail",
    title: "Course Detail",
    description: "Detailed course information",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "title", type: "string", example: "Web Development Fundamentals"),
        new OA\Property(property: "slug", type: "string", example: "web-development-fundamentals"),
        new OA\Property(property: "description", type: "string", example: "Complete course description"),
        new OA\Property(property: "short_description", type: "string", example: "Short course description"),
        new OA\Property(property: "thumbnail", type: "string", nullable: true, example: "http://example.com/storage/thumbnails/course1.jpg"),
        new OA\Property(property: "price", type: "number", format: "float", example: 99.99),
        new OA\Property(property: "discounted_price", type: "number", format: "float", nullable: true, example: 79.99),
        new OA\Property(property: "duration", type: "string", example: "8 weeks"),
        new OA\Property(property: "level", type: "string", enum: ["beginner", "intermediate", "advanced"], example: "beginner"),
        new OA\Property(property: "language", type: "string", example: "English"),
        new OA\Property(property: "status", type: "string", example: "published"),
        new OA\Property(property: "requirements", type: "array", items: new OA\Items(type: "string")),
        new OA\Property(property: "what_you_will_learn", type: "array", items: new OA\Items(type: "string")),
        new OA\Property(property: "total_lessons", type: "integer", example: 25),
        new OA\Property(property: "total_students", type: "integer", example: 150),
        new OA\Property(property: "average_rating", type: "number", format: "float", example: 4.5),
        new OA\Property(property: "total_reviews", type: "integer", example: 45),
        new OA\Property(property: "rating_distribution", type: "array", items: new OA\Items(
            properties: [
                new OA\Property(property: "rating", type: "integer", example: 5),
                new OA\Property(property: "count", type: "integer", example: 25),
                new OA\Property(property: "percentage", type: "number", format: "float", example: 55.6),
            ]
        )),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'instructor_id', 'category_id',
        'thumbnail', 'price', 'status', 'start_date', 'end_date', 'slug', 'is_featured', 'is_free', 'is_active', 'meta_title', 'meta_description', 'meta_keywords','thumb_video_url', 'discound_price', 'course_type',
    'bundle_courses',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
         'bundle_courses' => 'array',
    ];

    // Relations
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category()
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('order_index');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function enrollments()
    {
        return $this->hasManyThrough(Enrollment::class, Batch::class);
    }

    public function students()
    {
        return $this->hasManyThrough(User::class, Enrollment::class, 'batch_id', 'id', 'id', 'user_id')
            ->whereHas('enrollments', function ($query) {
                $query->whereHas('batch', function ($q) {
                    $q->where('course_id', $this->id);
                });
            });
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function discussions()
    {
        return $this->morphMany(Discussion::class, 'discussable');
    }

    public function lessons()
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    // Helper Functions
    public function isFree()
    {
        return $this->price == 0;
    }

    public function isPublished()
    {
        return $this->status === 'published';
    }

    public function getActiveBatchesCount()
    {
        return $this->batches()->where('is_active', true)->count();
    }

    public function getTotalStudents()
    {
        return $this->enrollments()->where('status', 'active')->count();
    }

    public function getTotalLessons()
    {
        return $this->lessons()->count();
    }

    public function getTotalQuizzes()
    {
        return $this->lessons()->where('type', 'quiz')->count();
    }

    public function getTotalRevenue()
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getCompletionRate()
    {
        $total = $this->enrollments()->count();
        if ($total == 0) {
            return 0;
        }

        $completed = $this->enrollments()->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }

    public function reviews()
    {
        return $this->hasMany(CourseReview::class);
    }

    // Exams where the user is the instructor
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'course_id');
    }

    public function isBundle(): bool
    {
        return $this->course_type === 'bundle';
    }

    public function getBundledCourses()
    {
        if (!$this->isBundle() || !$this->bundle_courses) {
            return collect();
        }

        return Course::whereIn('id', $this->bundle_courses)->get();
    }

    public function getBundlePrice(): float
    {
        if (!$this->isBundle()) {
            return $this->price;
        }

        return $this->getBundledCourses()->sum('price') * 0.85; // 15% bundle discount
    }

    public function getEffectivePrice(): float
    {
        // Return discounted price if available, otherwise regular price
        return $this->discounted_price ?? $this->price;
    }

    public function getBundleSavings(): float
    {
        if (!$this->isBundle()) {
            return 0;
        }

        $originalTotal = $this->getBundleOriginalPrice();
        $bundlePrice = $this->getEffectivePrice(); // Use bundle's own price/discounted_price

        return max(0, $originalTotal - $bundlePrice);
    }

    public function getBundleOriginalPrice(): float
    {
        if (!$this->isBundle()) {
            return $this->getEffectivePrice();
        }

        return $this->getBundledCourses()->sum(function ($course) {
            return $course->getEffectivePrice();
        });
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

}
