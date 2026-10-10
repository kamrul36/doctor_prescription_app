<?php

namespace App\Domain\Practice\Models;

use App\Domain\Practice\SpecialtyBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'user_id', 'name_en', 'name_bn', 'designation_en', 'designation_bn', 'reg_label', 'reg_no',
        'signature_path', 'default_template_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<DoctorCredential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(DoctorCredential::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<Chamber, $this> */
    public function chambers(): BelongsToMany
    {
        return $this->belongsToMany(Chamber::class, 'doctor_chambers')
            ->withPivot(['visiting_hours_en', 'visiting_hours_bn'])
            ->withTimestamps();
    }

    /** @return HasMany<DoctorChamberFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(DoctorChamberFee::class);
    }

    /**
     * Add-on specialties from the admin-managed list; none means a general physician only.
     *
     * @return BelongsToMany<Specialty, $this>
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'doctor_specialties')->withTimestamps()->orderBy('name');
    }

    /** @return list<int> */
    public function specialtyIds(): array
    {
        return $this->specialties->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Built-in prescription blocks this doctor's active specialties bring to the pad.
     *
     * @return list<SpecialtyBlock>
     */
    public function blocks(): array
    {
        return $this->specialties->where('is_active', true)->pluck('block')->filter()->unique()->values()->all();
    }

    /**
     * The doctor's assigned chambers that are still open, for the pad.
     *
     * @return Collection<int, Chamber>
     */
    public function activeChambers(): Collection
    {
        return $this->chambers->where('is_active', true)->sortBy('name_en')->values();
    }

    /** @return BelongsTo<PrescriptionTemplate, $this> */
    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(PrescriptionTemplate::class, 'default_template_id');
    }
}
