<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    /** The numeric code the legacy API exposed (and v1 clients still expect). */
    public function legacyCode(): int
    {
        return match ($this) {
            self::Open => 1,
            self::Confirmed => 2,
            self::Shipped => 3,
            self::Cancelled => 4,
        };
    }

    public static function fromLegacyCode(int $code): self
    {
        return match ($code) {
            1 => self::Open,
            2 => self::Confirmed,
            3 => self::Shipped,
            4 => self::Cancelled,
            default => throw new \ValueError("Unknown legacy status code {$code}"),
        };
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'gray',
            self::Confirmed => 'info',
            self::Shipped => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Open || $this === self::Confirmed;
    }
}
