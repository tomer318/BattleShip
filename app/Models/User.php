<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 
    'email', 
    'password', 
    'credits', 
    'gems',
    'inventory', 
    'daily_purchases', 
    'stats',
    'elo',
    'rank_tier',
    'pvp_wins',
    'pvp_losses',
    'accuracy_rate',
    'is_bot',
    'bot_difficulty',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'inventory' => 'array',
            'daily_purchases' => 'array',
            'stats' => 'array',
            'is_bot' => 'boolean',
            'elo' => 'integer',
            'pvp_wins' => 'integer',
            'pvp_losses' => 'integer',
            'accuracy_rate' => 'float',
        ];
    }
}