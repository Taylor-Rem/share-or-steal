<?php

namespace App\Enums;

enum SessionMode: string
{
    case Normal = 'normal';
    case Anonymous = 'anonymous';
}
