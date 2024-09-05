<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Title extends Model
{
    protected $fillable = [
        'chapter_id',
        'subtitle',
        'text'
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TitlePhoto::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(TitleFile::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(TitleLink::class);
    }
}
