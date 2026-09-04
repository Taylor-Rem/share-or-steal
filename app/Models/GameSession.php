<?php

namespace App\Models;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Models\Concerns\HasPreciseTimestamps;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class GameSession extends Model
{
    /** @use HasFactory<GameSessionFactory> */
    use HasFactory;

    use HasPreciseTimestamps;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mode' => SessionMode::class,
            'status' => SessionStatus::class,
            'fast_mode' => 'boolean',
            'phase_ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'analysis_beats' => 'array',
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('number');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(PlayerStat::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    /** Humans who are in the game: admitted and not kicked. The Machine is not one. */
    public function participants(): HasMany
    {
        return $this->players()->where('is_bot', false)->where('is_admitted', true)->whereNull('kicked_at');
    }

    /** `state.player_count`: non-bot, non-kicked players. */
    public function playerCount(): int
    {
        return $this->players()->where('is_bot', false)->whereNull('kicked_at')->count();
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    public function isAnonymous(): bool
    {
        return $this->mode === SessionMode::Anonymous;
    }

    /** @return array<string, int> */
    public function durations(): array
    {
        return config('game.durations.'.($this->fast_mode ? 'fast' : 'normal'));
    }

    /**
     * The `state` object that rides on every broadcast and on GET /api/sessions/{code}.
     * See CONTRACT.md § State.
     *
     * @return array<string, mixed>
     */
    public function toStateArray(): array
    {
        $beats = $this->analysis_beats;

        return [
            'code' => $this->code,
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'fast_mode' => $this->fast_mode,
            'paused' => $this->isPaused(),
            'rounds_count' => $this->rounds_count,
            'decisions_per_round' => $this->decisions_per_round,
            'round' => $this->current_round,
            'decision' => $this->current_decision,
            'analysis_beat' => $this->analysis_beat,
            'analysis_beat_count' => is_array($beats) ? count($beats) : null,
            'phase_ends_at' => self::iso($this->phase_ends_at),
            'player_count' => $this->playerCount(),
        ];
    }

    /**
     * SessionSummary for the director's history list: State plus the three lifecycle stamps.
     *
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        return $this->toStateArray() + [
            'created_at' => self::iso($this->created_at),
            'started_at' => self::iso($this->started_at),
            'ended_at' => self::iso($this->ended_at),
        ];
    }

    /** ISO-8601 UTC with milliseconds, the only timestamp format the contract uses. */
    public static function iso(?Carbon $time): ?string
    {
        return $time?->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function generateCode(): string
    {
        $alphabet = config('game.code_alphabet');
        $length = (int) config('game.code_length');

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
