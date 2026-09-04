<?php

namespace App\Enums;

/**
 * Awards, in the order they are revealed. Champion is revealed last, on the podium.
 */
enum AwardKey: string
{
    case Kindest = 'kindest';
    case MostForgiving = 'most_forgiving';
    case MostRuthless = 'most_ruthless';
    case BestPartner = 'best_partner';
    case MostBetrayed = 'most_betrayed';
    case ColdBlooded = 'cold_blooded';
    case EndgameAssassin = 'endgame_assassin';
    case Unreadable = 'unreadable';
    case FastestThumb = 'fastest_thumb';
    case Champion = 'champion';

    public function label(): string
    {
        return match ($this) {
            self::Kindest => 'Kindest',
            self::MostForgiving => 'Most Forgiving',
            self::MostRuthless => 'Most Ruthless',
            self::BestPartner => 'Best Partner',
            self::MostBetrayed => 'Most Betrayed',
            self::ColdBlooded => 'Cold Blooded',
            self::EndgameAssassin => 'Endgame Assassin',
            self::Unreadable => 'Unreadable',
            self::FastestThumb => 'Fastest Thumb',
            self::Champion => 'Champion',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Kindest => 'Highest share rate.',
            self::MostForgiving => 'Highest forgiveness rate, minimum three times stolen from.',
            self::MostRuthless => 'Highest steal rate.',
            self::BestPartner => 'People who played you walked away richest.',
            self::MostBetrayed => 'Shared, and got stolen from, more than anyone.',
            self::ColdBlooded => 'Most betrayals right after a mutual share.',
            self::EndgameAssassin => 'Most negative endgame shift.',
            self::Unreadable => 'Lowest predictability.',
            self::FastestThumb => 'Lowest average decision time.',
            self::Champion => 'Most total points.',
        };
    }

    /** The player_stats column this award ranks on, and the direction. */
    public function stat(): string
    {
        return match ($this) {
            self::Kindest => 'share_rate',
            self::MostForgiving => 'forgiveness',
            self::MostRuthless => 'share_rate',
            self::BestPartner => 'partner_yield',
            self::MostBetrayed => 'sucker_count',
            self::ColdBlooded => 'betrayals',
            self::EndgameAssassin => 'endgame_shift',
            self::Unreadable => 'predictability',
            self::FastestThumb => 'avg_response_ms',
            self::Champion => 'total_points',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::MostRuthless, self::EndgameAssassin, self::Unreadable, self::FastestThumb => 'lowest',
            default => 'highest',
        };
    }

    /** @return array{key: string, label: string, description: string} */
    public function toArray(): array
    {
        return ['key' => $this->value, 'label' => $this->label(), 'description' => $this->description()];
    }
}
