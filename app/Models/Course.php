<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Course extends Model
{
    protected $fillable = [
        'name',
        'creator_id',
        'description',
        'status',
        'cover_image_url',
        'price'
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function testSections(): HasMany
    {
        return $this->hasMany(TestSection::class);
    }

    public function access()
    {
        return $this->hasMany(CourseAccess::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}

