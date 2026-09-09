<?php

namespace App\Models;

use App\Models\Concerns\HasPreciseTimestamps;
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

    use HasPreciseTimestamps;

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

    /** The Machine's tit-for-tat needs no row lookups; everyone else is a phone. */
    public function isHuman(): bool
    {
        return ! $this->is_bot;
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
            'avatar' => $this->avatar(),
        ];
    }

    /**
     * The picked look, or The Machine's fixed one, or null.
     *
     * @return array{emoji: string, color: string}|null
     */
    public function avatar(): ?array
    {
        if ($this->is_bot) {
            return config('game.avatars.bot');
        }

        return $this->avatar_emoji && $this->avatar_color ? ['emoji' => $this->avatar_emoji, 'color' => $this->avatar_color] : null;
    }

    /**
     * DirectorPlayer: the public shape plus what the player list needs.
     *
     * @return array<string, mixed>
     */
    public function toDirectorArray(): array
    {
        return $this->toPublicArray() + $this->directorFields();
    }

    /**
     * The `director.player_updated` payload: the same fields, with the PublicPlayer nested.
     *
     * @return array<string, mixed>
     */
    public function toDirectorUpdateArray(): array
    {
        return ['player' => $this->toPublicArray()] + $this->directorFields();
    }

    /** @return array<string, mixed> */
    private function directorFields(): array
    {
        return [
            'is_admitted' => (bool) $this->is_admitted,
            'kicked' => $this->kicked_at !== null,
            'last_seen_at' => GameSession::iso($this->last_seen_at),
            'consecutive_timeouts' => (int) $this->consecutive_timeouts,
            'total_points' => (int) $this->total_points,
        ];
    }
}
