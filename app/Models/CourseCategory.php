<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Category",
    title: "Course Category",
    description: "Course category information",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Web Development"),
        new OA\Property(property: "slug", type: "string", example: "web-development"),
        new OA\Property(property: "description", type: "string", example: "Category description"),
        new OA\Property(property: "icon", type: "string", nullable: true, example: "fa-code"),
        new OA\Property(property: "courses_count", type: "integer", example: 15),
    ]
)]

class CourseCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    // Relations
    public function courses()
    {
        return $this->hasMany(Course::class, 'category_id');
    }

    // Helper Functions
    public function getActiveCoursesCount()
    {
        return $this->courses()->where('status', 'published')->count();
    }
}