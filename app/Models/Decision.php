<?php

namespace App\Models;

use App\Enums\Choice;
use Database\Factories\DecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Decision extends Model
{
    /** @use HasFactory<DecisionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'choice_a' => Choice::class,
            'choice_b' => Choice::class,
            'timed_out_a' => 'boolean',
            'timed_out_b' => 'boolean',
            'opened_at' => 'datetime',
            'deadline_at' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(Pairing::class);
    }

    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }
}
