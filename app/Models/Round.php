<?php

namespace App\Models;

use App\Models\Concerns\HasPreciseTimestamps;
use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    use HasPreciseTimestamps;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'anonymous' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(Pairing::class);
    }
}
