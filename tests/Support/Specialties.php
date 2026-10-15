<?php

namespace Tests\Support;

use App\Domain\Practice\Models\Specialty;

/** Specialties are created by users, so tests create the ones they need. */
final class Specialties
{
    private const NAMES = ['gynae' => 'Gynaecology & Obstetrics', 'dental' => 'Dental'];

    public static function make(string $code): Specialty
    {
        return Specialty::query()->firstOrCreate(['code' => $code], ['name' => self::NAMES[$code] ?? ucfirst($code)]);
    }

    /**
     * @param  list<string>  $codes
     * @return list<int>
     */
    public static function ids(array $codes): array
    {
        return array_map(fn (string $code) => self::make($code)->id, $codes);
    }
}
