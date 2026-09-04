<?php

namespace App\Models;

use App\Enums\Archetype;
use App\Models\Concerns\HasPreciseTimestamps;
use Database\Factories\PlayerStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStat extends Model
{
    /** @use HasFactory<PlayerStatFactory> */
    use HasFactory;

    use HasPreciseTimestamps;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'archetype' => Archetype::class,
            'share_rate' => 'float',
            'opening_move' => 'float',
            'retaliation' => 'float',
            'forgiveness' => 'float',
            'exploitation_rate' => 'float',
            'endgame_shift' => 'float',
            'predictability' => 'float',
            'partner_yield' => 'float',
            'match_rate' => 'float',
            'post_steal_share_rate' => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * The PlayerStats shape from CONTRACT.md § Analysis payloads.
     *
     * @return array<string, mixed>
     */
    public function toContractArray(): array
    {
        return [
            'player' => $this->player->toPublicArray(),
            'total_points' => $this->total_points,
            'rank' => $this->rank,
            'decisions_count' => $this->decisions_count,
            'timeouts' => $this->timeouts,
            'share_rate' => $this->share_rate,
            'opening_move' => $this->opening_move,
            'retaliation' => $this->retaliation,
            'forgiveness' => $this->forgiveness,
            'betrayals' => $this->betrayals,
            'exploitation' => $this->exploitation,
            'exploitation_rate' => $this->exploitation_rate,
            'endgame_shift' => $this->endgame_shift,
            'predictability' => $this->predictability,
            'partner_yield' => $this->partner_yield,
            'sucker_count' => $this->sucker_count,
            'times_stolen_from' => $this->times_stolen_from,
            'avg_response_ms' => $this->avg_response_ms,
            'archetype' => $this->archetype?->toArray(),
        ];
    }
}
