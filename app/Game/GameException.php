<?php

namespace App\Game;

use RuntimeException;

/**
 * A rule said no. `reason` is the contract's snake_case token (`not_enough_players`,
 * `deadline_passed`, ...); `status` is the HTTP status the API answers with.
 */
class GameException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '', public readonly int $status = 409)
    {
        parent::__construct($message !== '' ? $message : str_replace('_', ' ', ucfirst($reason)).'.');
    }

    public static function conflict(string $reason, string $message = ''): self
    {
        return new self($reason, $message, 409);
    }
}
