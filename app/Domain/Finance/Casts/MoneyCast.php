<?php

namespace App\Domain\Finance\Casts;

use App\Domain\Finance\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** Maps a decimal(12,2) column to a Money value object. */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::fromDecimal((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->toDecimal(),
            is_string($value) || is_int($value) => Money::fromDecimal($value)->toDecimal(),
            default => throw new InvalidArgumentException('Money attributes must be Money, string or int.'),
        };
    }
}
