<?php

namespace App\Events;

/** `analysis.started` — the last round ended and the analysis is computed. `{ beat_count }` */
class AnalysisStarted extends SessionBroadcast
{
    public function name(): string
    {
        return 'analysis.started';
    }
}
