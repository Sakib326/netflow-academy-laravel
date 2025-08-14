<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Review",
    title: "Course Review",
    description: "Course review information",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "rating", type: "integer", minimum: 1, maximum: 5, example: 5),
        new OA\Property(property: "title", type: "string", example: "Great course!"),
        new OA\Property(property: "review", type: "string", example: "Great course!"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(
            property: "user",
            properties: [
                new OA\Property(property: "id", type: "integer", example: 1),
                new OA\Property(property: "name", type: "string", example: "John Student"),
                new OA\Property(property: "avatar", type: "string", nullable: true, example: "http://example.com/storage/avatars/student1.jpg"),
            ]
        ),
        new OA\Property(
            property: "course",
            properties: [
                new OA\Property(property: "id", type: "integer", example: 1),
                new OA\Property(property: "title", type: "string", example: "Web Development Fundamentals"),
                new OA\Property(property: "slug", type: "string", example: "web-development-fundamentals"),
                new OA\Property(property: "thumbnail", type: "string", nullable: true, example: "http://example.com/storage/thumbnails/course1.jpg"),
            ]
        ),
    ]
)]

class CourseReview extends Model
{
    use HasFactory;

    protected $table = 'course_reviews';

    protected $fillable = [
        'user_id',
        'course_id',
        'rating',
        'title',
        'review',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
