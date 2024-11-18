<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TitleVideo extends Model
{
    use HasFactory;

    protected $table = 'title_videos';  // Указываем имя таблицы

    protected $fillable = [
        'title_text_id',  // Идентификатор текста или подраздела
        'video_url',      // URL видео
    ];

    public $timestamps = false;  // Если не используем автоматические временные метки
}

