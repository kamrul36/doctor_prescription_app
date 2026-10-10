<?php

namespace App\Domain\Clinical;

/** What one dose slot counts (printed after the M+N+E pattern). Bangla to be checked by a native speaker. */
enum DoseUnit: string
{
    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Ml = 'ml';
    case Spoon = 'spoon';
    case Drop = 'drop';
    case Piece = 'piece';
    case Puff = 'puff';
    case Sachet = 'sachet';
    case Injection = 'injection';
    case Application = 'application';

    public function label(): string
    {
        return match ($this) {
            self::Ml => 'ml',
            default => ucfirst($this->value),
        };
    }

    public function labelBn(): string
    {
        return match ($this) {
            self::Tablet => 'ট্যাবলেট',
            self::Capsule => 'ক্যাপসুল',
            self::Ml => 'মি.লি.',
            self::Spoon => 'চামচ',
            self::Drop => 'ফোঁটা',
            self::Piece => 'পিস',
            self::Puff => 'পাফ',
            self::Sachet => 'স্যাশে',
            self::Injection => 'ইনজেকশন',
            self::Application => 'বার লাগাবেন',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
