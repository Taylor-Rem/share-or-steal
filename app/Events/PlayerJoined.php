<?php

namespace App\Events;

/** `player.joined` — a player joined the lobby or was admitted. `{ player: PublicPlayer|null, player_count }`; `player` is null in anonymous mode. */
class PlayerJoined extends SessionBroadcast
{
    public function name(): string
    {
        return 'player.joined';
    }
}
