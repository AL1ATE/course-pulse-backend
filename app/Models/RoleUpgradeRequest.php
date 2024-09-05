<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleUpgradeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'requested_role',
        'status',
        'admin_id',
    ];

    // Связь с пользователем, сделавшим запрос
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Связь с администратором, который обработал запрос
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
