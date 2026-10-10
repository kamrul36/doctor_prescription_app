<?php

namespace App\Domain\Practice\Models;

use App\Domain\Practice\SpecialtyBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * An entry in the admin-managed specialty list. General practice is the base
 * every doctor has, so it is not an entry: a doctor or catalog item without a
 * specialty is general. Deactivated, never deleted (doctors and catalog items
 * point at it).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property SpecialtyBlock|null $block
 * @property bool $is_active
 * @property int|null $created_by
 */
class Specialty extends Model
{
    protected $fillable = ['code', 'name', 'block', 'is_active'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['block' => SpecialtyBlock::class, 'is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Doctor, $this> */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_specialties')->withTimestamps();
    }

    /**
     * @param  Builder<Specialty>  $query
     * @return Builder<Specialty>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Label for a nullable specialty, as shown in lists ("General" for none). */
    public static function labelFor(?self $specialty): string
    {
        return $specialty === null ? 'General' : $specialty->name;
    }

    /** @return Collection<int, Specialty> */
    public static function options(): Collection
    {
        return self::query()->active()->orderBy('name')->get();
    }

    /**
     * The list entry for a name a doctor typed: an existing one with the same
     * name (any case, inactive ones included so nothing is duplicated), or a
     * new one. Returns [specialty, created].
     *
     * @return array{Specialty, bool}
     */
    public static function findOrCreateByName(string $name): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $existing = self::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $specialty = new self(['name' => $name, 'code' => self::uniqueCode($name)]);
        $specialty->created_by = Auth::id();
        $specialty->save();

        return [$specialty, true];
    }

    public static function uniqueCode(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, '_') ?: 'specialty';
        $base = mb_substr($base, 0, 56);
        $code = $base;

        for ($i = 2; self::query()->where('code', $code)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        return $code;
    }
}
