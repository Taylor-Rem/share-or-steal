<?php

namespace App\Events;

/** `pairing.revealed` — the round's pairs. `{ round, anonymous, ends_at, pairs }`; `pairs` is `[]` in an anonymous round. */
class PairingRevealed extends SessionBroadcast
{
    public function name(): string
    {
        return 'pairing.revealed';
    }
}
