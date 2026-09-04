<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'is_admitted' => 'boolean',
            'kicked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PlayerStat::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function isActive(): bool
    {
        return $this->is_admitted && $this->kicked_at === null;
    }

    /**
     * The public shape of a player. Safe for the big screen in normal mode.
     * Never includes the device token.
     *
     * @return array{id: int, username: string, is_bot: bool}
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'is_bot' => $this->is_bot,
        ];
    }
}
