<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'player_ships' => 'array',
        'player_shots' => 'array',
        'bot_ships'    => 'array',
        'bot_shots'    => 'array',
        'bot_memory'   => 'array',
        'started_at'   => 'datetime',
    ];
}