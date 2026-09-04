<?php

namespace App\Auth;

use App\Models\Player;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who is making a request. Resolved from headers by IdentityResolver:
 *   X-Device-Token  -> a player (kind 'player')
 *   X-Director-Key  -> the director (kind 'director')
 *   X-Screen-Code   -> a big screen for that session code (kind 'screen')
 */
final class Identity implements Authenticatable
{
    public function __construct(
        public readonly string $kind,
        public readonly ?string $code = null,
        public readonly ?Player $player = null,
    ) {}

    public static function director(): self
    {
        return new self('director');
    }

    public static function screen(string $code): self
    {
        return new self('screen', strtoupper($code));
    }

    public static function player(Player $player): self
    {
        return new self('player', $player->session->code, $player);
    }

    public function isDirector(): bool
    {
        return $this->kind === 'director';
    }

    public function isScreenFor(string $code): bool
    {
        return $this->kind === 'screen' && $this->code === strtoupper($code);
    }

    public function isPlayerIn(string $code): bool
    {
        return $this->kind === 'player'
            && $this->player !== null
            && $this->player->isActive()
            && $this->code === strtoupper($code);
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->kind.':'.($this->player?->id ?? $this->code ?? '*');
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
