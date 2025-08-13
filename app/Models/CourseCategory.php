<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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