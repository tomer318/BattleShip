<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_code',
        'player1_id',
        'player2_id',
        'game_id',
        'status',
        'p1_ships',
        'p2_ships',
        'p1_ready',
        'p2_ready',
        'current_turn',
        'p1_shots',
        'p2_shots',
        'winner',
        'p1_active_skills',
        'p2_active_skills',
    ];

    protected $casts = [
        'p1_ships' => 'array',
        'p2_ships' => 'array',
        'p1_ready' => 'boolean',
        'p2_ready' => 'boolean',
        'p1_shots' => 'array',
        'p2_shots' => 'array',
        'p1_active_skills' => 'array',
        'p2_active_skills' => 'array',
    ];

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}