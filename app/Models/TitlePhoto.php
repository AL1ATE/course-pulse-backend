<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitlePhoto extends Model
{
    protected $fillable = [
        'title_id',
        'photo_url'
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}
