<?php

namespace App\Domain\Practice\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'user_id', 'name_en', 'name_bn', 'designation_en', 'designation_bn', 'reg_label', 'reg_no',
        'specialty_code', 'signature_path', 'default_template_id',
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

    /** @return BelongsTo<PrescriptionTemplate, $this> */
    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(PrescriptionTemplate::class, 'default_template_id');
    }
}
