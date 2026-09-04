<?php

namespace App\Enums;

/**
 * The session state machine. `paused` is not a status: a session is paused
 * when `paused_at` is set, and `paused_from_status` remembers where to resume.
 */
enum SessionStatus: string
{
    case Lobby = 'lobby';
    case Pairing = 'pairing';
    case Deciding = 'deciding';
    case Revealing = 'revealing';
    case RoundSummary = 'round_summary';
    case Analysis = 'analysis';
    case Finished = 'finished';

    /** Statuses the server clock advances on its own. */
    public function isTimed(): bool
    {
        return in_array($this, [self::Pairing, self::Deciding, self::Revealing, self::RoundSummary], true);
    }

    public function isPlaying(): bool
    {
        return $this->isTimed();
    }
}
