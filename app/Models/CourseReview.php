<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseReview extends Model
{
    use HasFactory;

    protected $table = 'course_reviews';

    protected $fillable = [
        'course_id',
        'user_id',
        'rating',
        'review',
        'update_count',
    ];

    protected $casts = [
        'review' => 'string',
        'update_count' => 'integer',
    ];
}
