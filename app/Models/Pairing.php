<?php

namespace App\Models;

use App\Models\Concerns\HasPreciseTimestamps;
use Database\Factories\PairingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pairing extends Model
{
    /** @use HasFactory<PairingFactory> */
    use HasFactory;

    use HasPreciseTimestamps;

    protected $guarded = [];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function playerA(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_a_id');
    }

    public function playerB(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_b_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class)->orderBy('index');
    }

    /** 'a' or 'b', or null if the player is not in this pairing. */
    public function seatOf(Player|int $player): ?string
    {
        $id = $player instanceof Player ? $player->id : $player;

        return match (true) {
            $id === $this->player_a_id => 'a',
            $id === $this->player_b_id => 'b',
            default => null,
        };
    }

    public function partnerOf(Player $player): Player
    {
        return $this->seatOf($player) === 'a' ? $this->playerB : $this->playerA;
    }
}
