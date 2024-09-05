<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseAccess extends Model
{
    protected $table = 'course_access'; // Явно указываем имя таблицы

    protected $fillable = [
        'course_id',
        'user_id',
        'access_end_date' // Добавлено новое поле
    ];

    protected $casts = [
        'access_end_date' => 'datetime', // Преобразование даты в объект Carbon
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
