<?php

namespace App\Domain\Practice\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorCredential extends Model
{
    protected $fillable = ['doctor_id', 'text_en', 'text_bn', 'sort_order'];
}
