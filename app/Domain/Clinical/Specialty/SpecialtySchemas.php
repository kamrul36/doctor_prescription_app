<?php

namespace App\Domain\Clinical\Specialty;

use App\Domain\Practice\SpecialtyBlock;

/** Block => schema, plus the validation rules and cleaning for `specialty_data`. */
final class SpecialtySchemas
{
    public static function for(SpecialtyBlock $block): SpecialtySchema
    {
        return match ($block) {
            SpecialtyBlock::Gynae => new GynaeHistory,
            SpecialtyBlock::Dental => new DentalFindings,
        };
    }

    /**
     * Rules for the blocks a doctor has. A block the doctor doesn't have is
     * rejected (the array rule lists only allowed keys).
     *
     * @param  list<SpecialtyBlock>  $blocks
     * @return array<string, list<mixed>>
     */
    public static function rules(array $blocks): array
    {
        $keys = array_map(fn (SpecialtyBlock $b) => $b->value, $blocks);
        $rules = ['specialty_data' => $keys === [] ? ['prohibited'] : ['nullable', 'array:'.implode(',', $keys)]];

        foreach ($blocks as $block) {
            $fields = self::for($block)->fields();
            $rules["specialty_data.{$block->value}"] = ['nullable', 'array:'.implode(',', array_keys($fields))];

            foreach ($fields as $key => $field) {
                $rules["specialty_data.{$block->value}.{$key}"] = ['nullable', ...$field['rules']];
            }
        }

        return $rules;
    }

    /**
     * Drops empty values and blocks; null when nothing was entered.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, array<string, mixed>>|null
     */
    public static function clean(?array $data): ?array
    {
        $clean = [];

        foreach ($data ?? [] as $block => $values) {
            $values = array_filter((array) $values, fn ($v) => $v !== null && $v !== '');
            if ($values !== []) {
                $clean[$block] = $values;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
