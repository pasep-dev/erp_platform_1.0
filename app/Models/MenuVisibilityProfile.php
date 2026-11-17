<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuVisibilityProfile extends Model
{
    protected $fillable = [
        'role_id',
        'user_id',
        'menu_keys',
    ];

    protected function casts(): array
    {
        return [
            'menu_keys' => 'array',
        ];
    }
}
