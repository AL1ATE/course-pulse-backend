<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Text extends Model
{
    use HasFactory;

    // Указываем имя таблицы
    protected $table = 'titles_texts';

    // Определяем первичный ключ
    protected $primaryKey = 'id';

    // Указываем, что таблица использует автоинкрементирование
    public $incrementing = true;

    // Указываем, что поля created_at/updated_at являются timestamps
    public $timestamps = true;

    // Определяем заполняемые поля
    protected $fillable = [
        'title_id',
        'content',
    ];

    // Определяем связь с моделью Title
    public function title()
    {
        return $this->belongsTo(Title::class, 'title_id');
    }

    // Определяем связь с моделью TitlePhoto
    public function photos()
    {
        return $this->hasMany(TitlePhoto::class, 'title_text_id'); // Корректируем поле связи
    }

    public function video()
    {
        return $this->hasMany(TitleVideo::class, 'title_text_id');
    }
}
