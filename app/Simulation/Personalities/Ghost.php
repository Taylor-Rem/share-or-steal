<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Drops the WebSocket mid-round 3 and reconnects later with the same device token. Expected: resumes; missed decisions are timeouts; nothing else changes. */
final class Ghost extends Base
{
    public function choose(History $history): ?Choice
    {
        return $this->copy($history);
    }

    public function offline(): ?array
    {
        return [3, 4, 6];
    }

    public function expectedArchetype(): ?string
    {
        return 'mirror';
    }
}
