<?php

// Models\User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OpenApi\Attributes as OA;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

#[OA\Schema(
    schema: "Instructor",
    title: "Instructor",
    description: "Instructor information",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "John Instructor"),
        new OA\Property(property: "email", type: "string", format: "email", example: "instructor@example.com"),
        new OA\Property(property: "avatar", type: "string", nullable: true, example: "http://example.com/storage/avatars/instructor1.jpg"),
        new OA\Property(property: "bio", type: "string", example: "Instructor biography"),
        new OA\Property(property: "role", type: "string", example: "instructor"),
        new OA\Property(property: "total_courses", type: "integer", example: 5),
        new OA\Property(property: "total_students", type: "integer", example: 250),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
    ]
)]

#[OA\Schema(
    schema: "User",
    title: "User",
    description: "User model",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "John Doe"),
        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
        new OA\Property(property: "role", type: "string", enum: ["admin", "instructor", "student", "moderator"], example: "student"),
        new OA\Property(property: "phone", type: "string", nullable: true, example: "+8801234567890"),
        new OA\Property(property: "avatar", type: "string", nullable: true, example: "avatars/user1.jpg"),
        new OA\Property(property: "is_active", type: "boolean", example: true),
        new OA\Property(property: "email_verified_at", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'avatar', 'is_active','bio','email_verified_at', 'designation'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'role' => 'string'
    ];

    // Relations
    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function batches()
    {
        return $this->hasManyThrough(Batch::class, Enrollment::class, 'user_id', 'id', 'id', 'batch_id');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function discussions()
    {
        return $this->hasMany(Discussion::class);
    }

    public function gradedSubmissions()
    {
        return $this->hasMany(Submission::class, 'graded_by');
    }

    // Helper Functions
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isInstructor()
    {
        return $this->role === 'instructor';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function hasAccessToCourse($courseId)
    {
        $course = Course::find($courseId);
        if (!$course || $course->price == 0) return true;
        
        return $this->payments()
            ->where('course_id', $courseId)
            ->where('status', 'completed')
            ->exists();
    }

    public function canAccessLesson($lessonId)
    {
        $lesson = Lesson::with('module.course')->find($lessonId);
        if (!$lesson) return false;
        if ($lesson->is_free) return true;
        
        return $this->hasAccessToCourse($lesson->module->course_id);
    }

    public function getEnrolledCourses()
    {
        return Course::whereHas('batches.enrollments', function($query) {
            $query->where('user_id', $this->id)->where('status', 'active');
        })->get();
    }

    public function getCourseProgress($courseId)
    {
        $enrollment = $this->enrollments()
            ->whereHas('batch', function($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })->first();
            
        return $enrollment ? $enrollment->progress_percentage : 0;
    }

     public function canAccessPanel(Panel $panel): bool
        {
            return $this->hasVerifiedEmail() && $this->role === 'admin';
        }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }
}