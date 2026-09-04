<?php

namespace App\Enums;

/**
 * The archetype ladder, in evaluation order. Thresholds live in config/game.php.
 */
enum Archetype: string
{
    case Saint = 'saint';
    case Wall = 'wall';
    case Backstabber = 'backstabber';
    case Grudge = 'grudge';
    case Mirror = 'mirror';
    case Diplomat = 'diplomat';
    case Opportunist = 'opportunist';
    case Wildcard = 'wildcard';
    case Pragmatist = 'pragmatist';

    public function label(): string
    {
        return match ($this) {
            self::Saint => 'The Saint',
            self::Wall => 'The Wall',
            self::Backstabber => 'The Backstabber',
            self::Grudge => 'The Grudge',
            self::Mirror => 'The Mirror',
            self::Diplomat => 'The Diplomat',
            self::Opportunist => 'The Opportunist',
            self::Wildcard => 'The Wildcard',
            self::Pragmatist => 'The Pragmatist',
        };
    }

    public function blurb(): string
    {
        return match ($this) {
            self::Saint => 'You shared no matter what it cost you.',
            self::Wall => 'You never let anyone in, and never got burned either.',
            self::Backstabber => 'Perfect partner right up until it stopped mattering.',
            self::Grudge => "One strike and they're done.",
            self::Mirror => 'Tit-for-tat: nice, retaliatory, forgiving, clear. The tournament winner.',
            self::Diplomat => 'You got stolen from and came back to the table anyway.',
            self::Opportunist => 'You shared until you smelled a sharer, then took the five.',
            self::Wildcard => 'Nobody could read you, including maybe you.',
            self::Pragmatist => 'You read the room and adjusted. Boring, effective.',
        };
    }

    /** @return array{key: string, label: string, blurb: string} */
    public function toArray(): array
    {
        return ['key' => $this->value, 'label' => $this->label(), 'blurb' => $this->blurb()];
    }
}
