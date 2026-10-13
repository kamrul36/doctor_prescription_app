<?php

namespace App\Domain\Practice\Models;

use App\Support\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Model;

class DoctorCredential extends Model
{
    use HasBilingualFields;

    protected $fillable = ['doctor_id', 'text_en', 'text_bn', 'sort_order'];
}
