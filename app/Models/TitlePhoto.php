<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitlePhoto extends Model
{
    protected $fillable = [
        'title_text_id', // Изменяем с 'title_id' на 'title_text_id'
        'photo_url'
    ];

    public function text(): BelongsTo
    {
        return $this->belongsTo(Text::class, 'title_text_id'); // Изменяем связь с Title на Text
    }
}
