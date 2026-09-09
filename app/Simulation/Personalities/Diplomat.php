<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Mirror, but forgives: after retaliating once, offers a share. Expected: The Diplomat; Most Forgiving. */
final class Diplomat extends Base
{
    public function choose(History $history): ?Choice
    {
        $last = $history->last();
        if ($last === null || $last['them'] === 'share') {
            return Choice::Share;
        }

        return $last['me'] === 'steal' ? Choice::Share : Choice::Steal;
    }

    public function expectedArchetype(): ?string
    {
        return 'diplomat';
    }
}
