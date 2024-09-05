<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleFile extends Model
{
    protected $fillable = [
        'title_id',
        'file_name',
        'file_url'
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}
