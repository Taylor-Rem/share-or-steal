<?php

namespace App\Models;

use App\Enums\AwardKey;
use App\Models\Concerns\HasPreciseTimestamps;
use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    use HasPreciseTimestamps;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'key' => AwardKey::class,
            'value' => 'float',
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
     * The Award shape from CONTRACT.md § 11.2. `value_label` here is a plain rendering;
     * Session 5 may refine it per award.
     *
     * @return array<string, mixed>
     */
    public function toContractArray(): array
    {
        $award = $this->key->toArray() + [
            'winner' => $this->player->toPublicArray(),
            'value' => $this->value,
            'value_label' => $this->valueLabel(),
            'tie_break' => $this->tie_break,
        ];
        if ($this->key === AwardKey::Champion) {
            $award['place'] = (int) $this->place;
        }

        return $award;
    }

    public function valueLabel(): string
    {
        if ($this->value === null) {
            return '—';
        }

        return match ($this->key) {
            AwardKey::Champion => (int) $this->value.' pts',
            AwardKey::BestPartner => number_format($this->value, 1).' pts',
            AwardKey::FastestThumb => (int) $this->value.' ms',
            AwardKey::Kindest, AwardKey::MostForgiving, AwardKey::MostRuthless,
            AwardKey::EndgameAssassin, AwardKey::Unreadable => round($this->value * 100).'%',
            default => (string) (int) $this->value,
        };
    }
}
