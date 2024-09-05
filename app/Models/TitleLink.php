<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleLink extends Model
{
    protected $fillable = [
        'title_id',
        'link_url'
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}
