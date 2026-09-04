<?php

namespace App\Events;

/** `analysis.beat` — one analysis beat, the `screen` half. `{ index, count, type, payload }` */
class AnalysisBeat extends SessionBroadcast
{
    public function name(): string
    {
        return 'analysis.beat';
    }
}
